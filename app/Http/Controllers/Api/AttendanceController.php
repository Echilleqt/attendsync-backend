<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricChallenge;
use App\Models\BiometricVerification;
use App\Models\Employee;
use App\Models\FaceVerificationToken;
use App\Models\Schedule;
use App\Services\AttendanceRuleService;
use App\Services\DtrService;
use App\Services\NetworkRestrictionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceRuleService $attendanceRuleService)
    {
    }

    public function timeIn(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, $request->input('entry_type') ?? 'time_in');
    }

    public function timeOut(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, $request->input('entry_type') ?? 'time_out');
    }

    public function record(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, $request->input('entry_type'));
    }

    public function morningIn(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, 'morning_in');
    }

    public function morningOut(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, 'morning_out');
    }

    public function afternoonIn(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, 'afternoon_in');
    }

    public function afternoonOut(Request $request): JsonResponse
    {
        return $this->processAttendanceRecord($request, 'afternoon_out');
    }

    /**
     * Centralized processor for the four-entry attendance workflow:
     * 1. Morning Time In
     * 2. Morning Time Out
     * 3. Afternoon Time In (target 1:00 PM)
     * 4. Afternoon Time Out
     */
    protected function processAttendanceRecord(Request $request, ?string $requestedAction): JsonResponse
    {
        app(NetworkRestrictionService::class)->validateOrThrow($request);

        $user = auth('api')->user();
        $employee = $user?->employee;

        if (
            !$user
            || $user->role !== 'employee'
            || !$employee
            || $employee->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        $validated = $request->validate([
            'employee_id' => ['prohibited'],
            'entry_type' => ['sometimes', 'nullable', 'string', 'in:morning_in,morning_out,afternoon_in,afternoon_out,am_in,am_out,pm_in,pm_out,time_in,time_out'],
            'verification_method' => ['sometimes', 'nullable', 'string', 'in:fingerprint,face'],
            'biometric_verification_token' => [
                'required_without:face_verification_token',
                'nullable',
                'string',
                'size:64',
            ],
            'face_verification_token' => [
                'required_without:biometric_verification_token',
                'nullable',
                'string',
                'size:64',
            ],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $photo = $request->file('photo');
        $imageInfo = @getimagesize($photo->getPathname());
        $actualMime = $photo->getMimeType();

        if (
            !$imageInfo
            || !in_array($imageInfo['mime'] ?? null, ['image/jpeg', 'image/png'], true)
            || $actualMime !== ($imageInfo['mime'] ?? null)
            || ($imageInfo[0] ?? 0) < 64
            || ($imageInfo[1] ?? 0) < 64
            || ($imageInfo[0] ?? 0) > 6000
            || ($imageInfo[1] ?? 0) > 6000
        ) {
            throw ValidationException::withMessages([
                'photo' => 'The attendance photo must be a valid JPEG or PNG image with dimensions between 64 and 6000 pixels.',
            ]);
        }

        $today = Carbon::today('Asia/Manila');
        $now = Carbon::now('Asia/Manila');
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $request,
                $employee,
                $validated,
                $photo,
                $today,
                $now,
                $requestedAction,
                &$storedPath
            ): JsonResponse {
                $usedMethod = $this->verifyBiometrics($request, $validated, $employee, $now);

                $attendance = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $today->toDateString())
                    ->lockForUpdate()
                    ->first();

                // Check schedule & late threshold using effective schedule for today
                $applicableSchedule = $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($employee, $today);

                if (!$applicableSchedule) {
                    throw ValidationException::withMessages([
                        'schedule' => 'No valid work schedule is assigned to your profile. Please contact the administrator.',
                    ]);
                }

                $scheduleTimes = $this->attendanceRuleService->getScheduleTimes($applicableSchedule, $today);

                // Determine target entry action based on request and current state
                $targetAction = $this->resolveEntryAction($attendance, $requestedAction ?? $validated['entry_type'] ?? null, $applicableSchedule, $now);

                // Enforce sequence strictly
                $this->assertSequenceValid($attendance, $targetAction, $applicableSchedule, $now);

                // Determine photo folder
                $folder = match ($targetAction) {
                    'morning_in' => 'attendance-proofs',
                    'morning_out' => 'attendance-proofs/morning-out',
                    'afternoon_in' => 'attendance-proofs/afternoon-in',
                    'afternoon_out', 'legacy_time_out' => 'attendance-proofs/time-out',
                };

                $storedPath = $photo->store($folder, 'local');
                if ($storedPath === false) {
                    throw new RuntimeException('Attendance photo storage failed.');
                }

                if (!$attendance) {
                    $attendance = new Attendance();
                    $attendance->employee_id = $employee->id;
                    $attendance->attendance_date = $today->toDateString();
                    $attendance->status = 'present';
                }

                $attendance->schedule_id = $applicableSchedule->id;

                if ($targetAction === 'morning_in') {
                    $attendance->am_time_in = $now;
                    $attendance->time_in = $now;
                    $attendance->am_time_in_photo_path = $storedPath;
                    $attendance->photo_path = $storedPath;
                    $attendance->am_time_in_verification_method = $usedMethod;
                    $attendance->time_in_verification_method = $usedMethod;
                    $attendance->late_starting_time_snapshot = $scheduleTimes['late_cutoff_am']?->format('H:i:s');
                    $isLateAm = $scheduleTimes['late_cutoff_am'] !== null && $now->format('H:i:s') > $scheduleTimes['late_cutoff_am']->format('H:i:s');
                    $attendance->am_status = $isLateAm ? 'late' : 'present';
                    $attendance->status = $attendance->am_status;
                } elseif ($targetAction === 'morning_out') {
                    $attendance->am_time_out = $now;
                    $attendance->am_time_out_photo_path = $storedPath;
                    $attendance->am_time_out_verification_method = $usedMethod;

                    // AM session status complete
                    $isLateAm = $scheduleTimes['late_cutoff_am'] !== null && ($attendance->am_time_in?->format('H:i:s') ?? '00:00:00') > $scheduleTimes['late_cutoff_am']->format('H:i:s');
                    $attendance->am_status = $isLateAm ? 'late' : 'present';
                    $attendance->status = $this->attendanceRuleService->computeOverallStatus($attendance->am_status, $attendance->pm_status);
                } elseif ($targetAction === 'afternoon_in') {
                    $attendance->pm_time_in = $now;
                    if (empty($attendance->time_in)) {
                        $attendance->time_in = $now;
                    }
                    if (empty($attendance->photo_path)) {
                        $attendance->photo_path = $storedPath;
                    }
                    if (empty($attendance->time_in_verification_method)) {
                        $attendance->time_in_verification_method = $usedMethod;
                    }
                    $attendance->pm_time_in_photo_path = $storedPath;
                    $attendance->pm_time_in_verification_method = $usedMethod;

                    $isLatePm = $scheduleTimes['late_cutoff_pm'] !== null && $now->format('H:i:s') > $scheduleTimes['late_cutoff_pm']->format('H:i:s');
                    $attendance->pm_status = $isLatePm ? 'late' : 'present';

                    // If morning session was never timed in
                    if (empty($attendance->am_time_in)) {
                        $attendance->am_status = 'absent';
                    }

                    $attendance->status = $this->attendanceRuleService->computeOverallStatus($attendance->am_status, $attendance->pm_status);
                } elseif ($targetAction === 'afternoon_out') {
                    $attendance->pm_time_out = $now;
                    $attendance->time_out = $now;
                    $attendance->pm_time_out_photo_path = $storedPath;
                    $attendance->time_out_photo_path = $storedPath;
                    $attendance->pm_time_out_verification_method = $usedMethod;
                    $attendance->time_out_verification_method = $usedMethod;

                    // PM session status complete
                    $isLatePm = $scheduleTimes['late_cutoff_pm'] !== null && ($attendance->pm_time_in?->format('H:i:s') ?? '00:00:00') > $scheduleTimes['late_cutoff_pm']->format('H:i:s');
                    $attendance->pm_status = $isLatePm ? 'late' : 'present';
                    $attendance->status = $this->attendanceRuleService->computeOverallStatus($attendance->am_status, $attendance->pm_status);
                } elseif ($targetAction === 'legacy_time_out') {
                    $attendance->time_out = $now;
                    $attendance->time_out_photo_path = $storedPath;
                    $attendance->time_out_verification_method = $usedMethod;
                }

                if ($targetAction !== 'legacy_time_out') {
                    $attendance->undertime_minutes = $this->attendanceRuleService->calculateUndertime(
                        $attendance,
                        $applicableSchedule,
                        $today
                    );
                }
                $attendance->save();

                $label = match ($targetAction) {
                    'morning_in' => 'Time In',
                    'morning_out' => 'Morning Time Out',
                    'afternoon_in' => 'Afternoon Time In',
                    'afternoon_out', 'legacy_time_out' => 'Time Out',
                };

                $statusCode = ($targetAction === 'morning_in' || $requestedAction === 'time_in') ? 201 : 200;

                return response()->json([
                    'success' => true,
                    'message' => "{$label} recorded successfully.",
                    'action' => $targetAction,
                    'attendance' => $this->attendancePayload($attendance->fresh()),
                ], $statusCode);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Resolve the entry action from input or current attendance state.
     */
    protected function resolveEntryAction(?Attendance $attendance, ?string $hint, ?Schedule $schedule = null, ?Carbon $now = null): string
    {
        $normalized = match ($hint) {
            'morning_in', 'am_in' => 'morning_in',
            'morning_out', 'am_out' => 'morning_out',
            'afternoon_in', 'pm_in' => 'afternoon_in',
            'afternoon_out', 'pm_out' => 'afternoon_out',
            default => null,
        };

        if ($normalized) {
            return $normalized;
        }

        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $scheduleTimes = $schedule ? $this->attendanceRuleService->getScheduleTimes($schedule, $now) : null;
        $amCutoff = $scheduleTimes['am_time_out'] ?? Carbon::parse($now->toDateString() . ' 12:00:00', 'Asia/Manila');

        // If hint is generic 'time_in':
        if ($hint === 'time_in') {
            if ($this->isLegacyTwoEntryRecord($attendance)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Time In has already been recorded for today.',
                ]);
            }
            if (!$attendance || !$attendance->am_time_in) {
                // If past morning shift, this time_in is for afternoon
                if ($now->greaterThanOrEqualTo($amCutoff)) {
                    if ($attendance && $attendance->pm_time_in) {
                        throw ValidationException::withMessages([
                            'attendance' => 'Afternoon Time In has already been recorded for today.',
                        ]);
                    }
                    return 'afternoon_in';
                }
                return 'morning_in';
            }
            if ($attendance->am_time_out && !$attendance->pm_time_in) {
                return 'afternoon_in';
            }
            if ($attendance->am_time_in && !$attendance->am_time_out) {
                if ($now->greaterThanOrEqualTo($amCutoff) && !$attendance->pm_time_in) {
                    return 'afternoon_in';
                }
                throw ValidationException::withMessages([
                    'attendance' => 'An attendance record or proof photo already exists for today.',
                ]);
            }
            throw ValidationException::withMessages([
                'attendance' => 'Time In has already been recorded for both morning and afternoon today.',
            ]);
        }

        // If hint is generic 'time_out':
        if ($hint === 'time_out') {
            if ($this->isLegacyTwoEntryRecord($attendance)) {
                if ($attendance->time_out) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Time Out has already been recorded for today.',
                    ]);
                }
                if (
                    !$scheduleTimes
                    || $now->lessThanOrEqualTo($attendance->time_in)
                    || $now->lessThan($scheduleTimes['pm_time_in'])
                    || $now->greaterThan($scheduleTimes['pm_time_out'])
                ) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Legacy Time Out must follow Time In and occur within the configured afternoon session.',
                    ]);
                }

                return 'legacy_time_out';
            }

            $hasTimeIn = $attendance && ($attendance->am_time_in || $attendance->pm_time_in);
            if (!$hasTimeIn) {
                throw ValidationException::withMessages([
                    'attendance' => 'Please record your Time In before recording Time Out.',
                ]);
            }
            if ($attendance->pm_time_in) {
                if ($attendance->pm_time_out) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Time Out has already been recorded for today.',
                    ]);
                }
                return 'afternoon_out';
            }
            if ($attendance->am_time_out && !$now->greaterThanOrEqualTo($amCutoff)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Time Out has already been recorded for today.',
                ]);
            }
            if ($now->lessThan($amCutoff)) {
                return 'morning_out';
            }
            if ($attendance->pm_time_out || ($attendance->time_out && $attendance->pm_time_out)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Time Out has already been recorded for today.',
                ]);
            }
            return 'afternoon_out';
        }

        // If no hint provided, automatically determine the next sequential action
        if ($this->isLegacyTwoEntryRecord($attendance)) {
            return $attendance->time_out ? 'complete' : 'legacy_time_out';
        }
        return $this->determineNextAction($attendance, $schedule, $now);
    }

    /**
     * Enforce strict sequence on the server.
     */
    protected function assertSequenceValid(?Attendance $attendance, string $targetAction, ?Schedule $schedule = null, ?Carbon $now = null): void
    {
        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $scheduleTimes = $schedule ? $this->attendanceRuleService->getScheduleTimes($schedule, $now) : null;
        $amCutoff = $scheduleTimes['am_time_out'] ?? Carbon::parse($now->toDateString() . ' 12:00:00', 'Asia/Manila');

        switch ($targetAction) {
            case 'morning_in':
                if ($scheduleTimes && $now->greaterThanOrEqualTo($scheduleTimes['am_time_out'])) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Morning Time In must be recorded during the configured morning session.',
                    ]);
                }
                if ($attendance && ($attendance->am_time_in || $attendance->photo_path)) {
                    throw ValidationException::withMessages([
                        'attendance' => 'An attendance record or proof photo already exists for today.',
                    ]);
                }
                break;

            case 'morning_out':
                if (!$attendance || !$attendance->am_time_in) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Please record your Morning Time In before recording Morning Time Out.',
                    ]);
                }
                if ($attendance->am_time_out) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Morning Time Out has already been recorded for today.',
                    ]);
                }
                $amIn = $attendance->am_time_in;
                if ($scheduleTimes && (
                    $now->lessThanOrEqualTo($amIn)
                    || $now->greaterThan($scheduleTimes['am_time_out'])
                )) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Morning Time Out must follow Morning Time In and occur no later than the configured morning session end.',
                    ]);
                }
                break;

            case 'afternoon_in':
                if ($scheduleTimes && $now->greaterThanOrEqualTo($scheduleTimes['pm_time_out'])) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Afternoon Time In must be recorded during the configured afternoon session.',
                    ]);
                }
                if ($attendance && $attendance->pm_time_in) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Afternoon Time In has already been recorded for today.',
                    ]);
                }
                // If it's before morning departure and morning in exists without morning out, prompt for morning out
                if ($attendance && $attendance->am_time_in && !$attendance->am_time_out && $now->lessThan($amCutoff)) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Please record your Morning Time Out before recording Afternoon Time In.',
                    ]);
                }
                break;

            case 'afternoon_out':
                if (!$attendance || (!$attendance->am_time_in && !$attendance->pm_time_in)) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Please record your Time In before recording Time Out.',
                    ]);
                }
                if (!$attendance->pm_time_in) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Please record Afternoon Time In before recording Afternoon Time Out.',
                    ]);
                }
                if ($attendance->pm_time_out || ($attendance->time_out && $attendance->pm_time_out)) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Time Out has already been recorded for today.',
                    ]);
                }
                if ($scheduleTimes && (
                    $now->lessThanOrEqualTo($attendance->pm_time_in)
                    || $now->greaterThan($scheduleTimes['pm_time_out'])
                )) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Afternoon Time Out must follow Afternoon Time In and occur no later than the configured afternoon session end.',
                    ]);
                }
                break;

            case 'legacy_time_out':
                if (!$this->isLegacyTwoEntryRecord($attendance)) {
                    throw ValidationException::withMessages([
                        'attendance' => 'This record does not qualify for legacy two-entry compatibility.',
                    ]);
                }
                if (
                    !$scheduleTimes
                    || $now->lessThanOrEqualTo($attendance->time_in)
                    || $now->lessThan($scheduleTimes['pm_time_in'])
                    || $now->greaterThan($scheduleTimes['pm_time_out'])
                ) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Legacy Time Out must follow Time In and occur within the configured afternoon session.',
                    ]);
                }
                break;

            case 'complete':
                throw ValidationException::withMessages([
                    'attendance' => 'All four attendance entries for today have already been completed.',
                ]);
        }
    }

    public function determineNextAction(?Attendance $attendance, ?Schedule $schedule = null, ?Carbon $now = null): string
    {
        if ($this->isLegacyTwoEntryRecord($attendance)) {
            return $attendance->time_out ? 'complete' : 'time_out';
        }

        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $scheduleTimes = $schedule ? $this->attendanceRuleService->getScheduleTimes($schedule, $now) : null;
        $amCutoff = $scheduleTimes['am_time_out'] ?? Carbon::parse($now->toDateString() . ' 12:00:00', 'Asia/Manila');

        if (!$attendance) {
            if ($now->greaterThanOrEqualTo($amCutoff)) {
                return 'afternoon_in';
            }
            return 'morning_in';
        }

        if (!$attendance->am_time_in) {
            if ($now->greaterThanOrEqualTo($amCutoff)) {
                return $attendance->pm_time_in ? ($attendance->pm_time_out ? 'complete' : 'afternoon_out') : 'afternoon_in';
            }
            return 'morning_in';
        }

        if (!$attendance->am_time_out) {
            $pmStart = $scheduleTimes['pm_time_in'] ?? Carbon::parse($now->toDateString() . ' 13:00:00', 'Asia/Manila');
            if ($now->lessThan($pmStart)) {
                return 'morning_out';
            }
            if (!$attendance->pm_time_in) {
                return 'afternoon_in';
            }
        }

        if (!$attendance->pm_time_in) {
            return 'afternoon_in';
        }

        if (!$attendance->pm_time_out) {
            return 'afternoon_out';
        }

        return 'complete';
    }

    private function verifyBiometrics(Request $request, array $validated, Employee $employee, Carbon $now): string
    {
        $explicitMethod = $validated['verification_method'] ?? null;
        $fingerprintToken = $validated['biometric_verification_token'] ?? null;
        $faceToken = $validated['face_verification_token'] ?? null;

        if (
            ($explicitMethod === 'fingerprint' && $faceToken)
            || ($explicitMethod === 'face' && $fingerprintToken)
            || (!$explicitMethod && $fingerprintToken && $faceToken)
        ) {
            throw ValidationException::withMessages([
                'verification_method' => 'Choose one biometric method and provide only its verification token.',
            ]);
        }

        if (config('face-recognition.require_for_attendance', false) && !$faceToken) {
            throw ValidationException::withMessages([
                'face_verification_token' => 'Face verification is required before attendance submission.',
            ]);
        }

        $method = $explicitMethod ?: ($faceToken && !$fingerprintToken ? 'face' : 'fingerprint');

        if ($method === 'face') {
            if (!$faceToken) {
                throw ValidationException::withMessages([
                    'face_verification_token' => 'Face verification token is required when face recognition is selected.',
                ]);
            }

            $faceVerification = FaceVerificationToken::query()
                ->where('token_hash', hash('sha256', $faceToken))
                ->where('employee_id', $employee->id)
                ->where('purpose', 'attendance')
                ->where('device_uuid', $request->header('X-Device-Id'))
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->lockForUpdate()
                ->first();

            if (!$faceVerification) {
                throw ValidationException::withMessages([
                    'face_verification_token' => 'Face verification is invalid, expired, or already used.',
                ]);
            }

            $faceVerification->used_at = $now;
            $faceVerification->save();

            return 'face';
        }

        if (!$fingerprintToken) {
            throw ValidationException::withMessages([
                'biometric_verification_token' => 'Biometric verification token is required when fingerprint is selected.',
            ]);
        }

        $approvedDeviceId = $request->attributes->get('approved_device_id');
        if (!$approvedDeviceId) {
            throw ValidationException::withMessages([
                'biometric_verification_token' => 'Fingerprint verification requires the currently approved device.',
            ]);
        }

        $fingerprintVerification = BiometricVerification::query()
            ->where('token_hash', hash('sha256', $fingerprintToken))
            ->where('employee_id', $employee->id)
            ->whereHas('credential', function ($query) use ($approvedDeviceId): void {
                $query->where(
                    'approved_device_id',
                    $approvedDeviceId
                )->whereNull('revoked_at')
                    ->whereNotNull('verified_at');
            })
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first();

        if (!$fingerprintVerification) {
            throw ValidationException::withMessages([
                'biometric_verification_token' => 'Biometric verification is invalid, expired, or already used.',
            ]);
        }

        $fingerprintVerification->used_at = $now;
        $fingerprintVerification->save();

        return 'fingerprint';
    }

    public function today(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $today = Carbon::today('Asia/Manila');
        $now = Carbon::now('Asia/Manila');
        $schedule = $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($employee, $today);

        $attendance = Attendance::query()
            ->with('schedule')
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        $scheduleData = $schedule ? [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'am_time_in' => $schedule->am_time_in,
            'am_time_out' => $schedule->am_time_out,
            'pm_time_in' => $schedule->pm_time_in,
            'pm_time_out' => $schedule->pm_time_out,
            'late_threshold_am' => $schedule->late_threshold_am,
            'late_threshold_pm' => $schedule->late_threshold_pm,
            'grace_period_minutes' => (int) ($schedule->grace_period_minutes ?? 0),
        ] : null;

        return response()->json([
            'success' => true,
            'attendance' => $this->attendancePayload($attendance, $schedule),
            'schedule' => $scheduleData,
            'next_action' => $this->determineNextAction($attendance, $schedule, $now),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee' || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:present,late,absent,incomplete,on_leave'],
            'range' => ['nullable', 'in:this_week,this_month,all'],
        ]);

        $range = $validated['range'] ?? null;
        $now = Carbon::now('Asia/Manila');

        if ($range === 'this_week') {
            $fromDate = $now->copy()->startOfWeek()->toDateString();
            $toDate = $now->copy()->endOfWeek()->toDateString();
        } elseif ($range === 'this_month') {
            $fromDate = $now->copy()->startOfMonth()->toDateString();
            $toDate = $now->copy()->endOfMonth()->toDateString();
        } else {
            $fromDate = $validated['from_date'] ?? null;
            $toDate = $validated['to_date'] ?? null;
        }

        $query = Attendance::query()->with(['schedule', 'employee'])->where('employee_id', $employee->id);

        if ($fromDate && $toDate) {
            $query->whereBetween('attendance_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $query->where('attendance_date', '>=', $fromDate);
        } elseif ($toDate) {
            $query->where('attendance_date', '<=', $toDate);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $records = (clone $query)->orderByDesc('attendance_date')->get();

        $summaryQuery = Attendance::query()->where('employee_id', $employee->id);
        if ($fromDate && $toDate) {
            $summaryQuery->whereBetween('attendance_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $summaryQuery->where('attendance_date', '>=', $fromDate);
        } elseif ($toDate) {
            $summaryQuery->where('attendance_date', '<=', $toDate);
        }

        $presentCount = (clone $summaryQuery)->where('status', 'present')->count();
        $lateCount = (clone $summaryQuery)->where('status', 'late')->count();
        $absentCount = (clone $summaryQuery)->where('status', 'absent')->count();
        $incompleteCount = (clone $summaryQuery)->where('status', 'incomplete')->count();
        $onLeaveCount = (clone $summaryQuery)->where('status', 'on_leave')->count();
        $totalRecords = $presentCount + $lateCount + $absentCount + $incompleteCount + $onLeaveCount;

        $formattedRecords = $records->map(function (Attendance $attendance) {
            return $this->attendancePayload($attendance);
        });

        return response()->json([
            'success' => true,
            'records' => $formattedRecords,
            'summary' => [
                'total' => $totalRecords,
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'incomplete' => $incompleteCount,
                'on_leave' => $onLeaveCount,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function dtr(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee' || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $validated['month'] ?? Carbon::now('Asia/Manila')->format('Y-m');
        $dtr = app(DtrService::class)->generateMonthlyDtr($employee, $month);

        return response()->json([
            'success' => true,
            'dtr' => $dtr,
        ]);
    }

    private function attendancePayload(?Attendance $attendance, ?Schedule $schedule = null): ?array
    {
        if (!$attendance) {
            return null;
        }

        $schedule = $attendance->schedule
            ?? ($attendance->employee ? $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($attendance->employee, $attendance->attendance_date) : null)
            ?? $schedule;

        $amIn = $attendance->am_time_in;
        $amOut = $attendance->am_time_out;
        $pmIn = $attendance->pm_time_in;
        $pmOut = $attendance->pm_time_out;

        $scheduleData = $schedule ? [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'am_time_in' => $schedule->am_time_in,
            'am_time_out' => $schedule->am_time_out,
            'pm_time_in' => $schedule->pm_time_in,
            'pm_time_out' => $schedule->pm_time_out,
            'late_threshold_am' => $schedule->late_threshold_am,
            'late_threshold_pm' => $schedule->late_threshold_pm,
            'grace_period_minutes' => (int) ($schedule->grace_period_minutes ?? 0),
        ] : null;

        $now = Carbon::now('Asia/Manila');

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'schedule_id' => $attendance->schedule_id,
            'schedule_name' => $schedule?->name ?? 'Standard',
            'schedule' => $scheduleData,
            'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
            // Backward compatible 2-entry timestamps
            'time_in' => ($this->isLegacyTwoEntryRecord($attendance) ? $attendance->time_in : $amIn)
                ?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'time_out' => ($this->isLegacyTwoEntryRecord($attendance) ? $attendance->time_out : $attendance->pm_time_out)
                ?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'time_in_verification_method' => $attendance->time_in_verification_method,
            'time_out_verification_method' => $attendance->time_out_verification_method,
            // 4-entry attendance timestamps
            'am_time_in' => $amIn?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'am_time_out' => $amOut?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'pm_time_in' => $pmIn?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'pm_time_out' => $attendance->pm_time_out?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'am_status' => $attendance->am_status,
            'pm_status' => $attendance->pm_status,
            'status' => $attendance->status,
            'am_time_in_verification_method' => $attendance->am_time_in_verification_method ?? $attendance->time_in_verification_method,
            'am_time_out_verification_method' => $attendance->am_time_out_verification_method,
            'pm_time_in_verification_method' => $attendance->pm_time_in_verification_method,
            'pm_time_out_verification_method' => $attendance->pm_time_out_verification_method ?? $attendance->time_out_verification_method,
            'next_action' => $this->determineNextAction($attendance, $schedule, $now),
            'undertime_minutes' => $attendance->undertime_minutes ?? 0,
            'timezone' => 'Asia/Manila',
            'photo_url' => ($attendance->photo_path || $attendance->am_time_in_photo_path)
                ? route('api.attendance.photo', ['attendance' => $attendance->id], false)
                : null,
            'time_out_photo_url' => ($attendance->time_out_photo_path || $attendance->pm_time_out_photo_path)
                ? route('api.attendance.time-out-photo', ['attendance' => $attendance->id], false)
                : null,
            'am_time_out_photo_url' => $attendance->am_time_out_photo_path
                ? route('api.attendance.entry-photo', ['attendance' => $attendance->id, 'entry' => 'am-out'], false)
                : null,
            'pm_time_in_photo_url' => $attendance->pm_time_in_photo_path
                ? route('api.attendance.entry-photo', ['attendance' => $attendance->id, 'entry' => 'pm-in'], false)
                : null,
            'late_starting_time_snapshot' => $attendance->late_starting_time_snapshot,
            'created_at' => $attendance->created_at?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'updated_at' => $attendance->updated_at?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
        ];
    }

    private function isLegacyTwoEntryRecord(?Attendance $attendance): bool
    {
        return $attendance !== null
            && $attendance->time_in !== null
            && $attendance->am_time_in === null
            && $attendance->am_time_out === null
            && $attendance->pm_time_in === null
            && $attendance->pm_time_out === null;
    }

    public function photo(Attendance $attendance)
    {
        return $this->attendancePhotoResponse($attendance, $attendance->am_time_in_photo_path ?? $attendance->photo_path);
    }

    public function timeOutPhoto(Attendance $attendance)
    {
        return $this->attendancePhotoResponse($attendance, $attendance->pm_time_out_photo_path ?? $attendance->time_out_photo_path);
    }

    public function entryPhoto(Attendance $attendance, string $entry)
    {
        $path = match ($entry) {
            'am-in' => $attendance->am_time_in_photo_path ?? $attendance->photo_path,
            'am-out' => $attendance->am_time_out_photo_path,
            'pm-in' => $attendance->pm_time_in_photo_path,
            'pm-out' => $attendance->pm_time_out_photo_path ?? $attendance->time_out_photo_path,
            default => null,
        };

        return $this->attendancePhotoResponse($attendance, $path);
    }

    private function attendancePhotoResponse(Attendance $attendance, ?string $photoPath)
    {
        $user = auth()->user();

        if (
            !$user
            || (
                $user->role !== 'admin'
                && (
                    !$user->employee
                    || (int) $user->employee->id !== (int) $attendance->employee_id
                )
            )
        ) {
            abort(403);
        }

        if (!$photoPath || !Storage::disk('local')->exists($photoPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance photo not found.',
            ], 404);
        }

        $path = Storage::disk('local')->path($photoPath);
        $mime = mime_content_type($path);

        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Stored attendance photo has an unsupported format.',
            ], 415);
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
