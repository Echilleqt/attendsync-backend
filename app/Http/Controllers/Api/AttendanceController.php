<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricVerification;
use App\Models\Employee;
use App\Models\FaceVerificationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AttendanceController extends Controller
{
    public function timeIn(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user->employee;

        if (
            $user->role !== 'employee'
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
                $employee,
                $validated,
                $photo,
                $today,
                $now,
                &$storedPath
            ): JsonResponse {
                $usedMethod = $this->verifyBiometrics($validated, $employee, $now);

                $existingAttendance = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $today->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existingAttendance?->time_in || $existingAttendance?->photo_path) {
                    throw ValidationException::withMessages([
                        'attendance' => 'An attendance record or proof photo already exists for today.',
                    ]);
                }

                $setting = AttendanceSetting::query()
                    ->whereDate('effective_date', '<=', $today->toDateString())
                    ->orderByDesc('effective_date')
                    ->first();

                $threshold = $setting
                    ? Carbon::parse($setting->late_starting_time, 'Asia/Manila')->format('H:i:s')
                    : null;

                $attendance = $existingAttendance ?? new Attendance();
                $attendance->employee_id = $employee->id;
                $attendance->attendance_date = $today->toDateString();
                $attendance->time_in = $now;
                $attendance->status = $threshold !== null && $now->format('H:i:s') > $threshold
                    ? 'late'
                    : 'present';
                $attendance->late_starting_time_snapshot = $threshold;
                $attendance->time_in_verification_method = $usedMethod;

                $storedPath = $photo->store('attendance-proofs', 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('Attendance photo storage failed.');
                }

                $attendance->photo_path = $storedPath;
                $attendance->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Time In recorded successfully.',
                    'attendance' => $this->attendancePayload($attendance->fresh()),
                ], 201);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                if (!Storage::disk('local')->delete($storedPath)) {
                    report(new RuntimeException('Failed to clean up an attendance photo after a database error.'));
                }
            }

            throw $exception;
        }
    }

    public function timeOut(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user->employee;

        if (
            $user->role !== 'employee'
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
                $employee,
                $validated,
                $photo,
                $today,
                $now,
                &$storedPath
            ): JsonResponse {
                $usedMethod = $this->verifyBiometrics($validated, $employee, $now);

                $attendance = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $today->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (!$attendance || !$attendance->time_in) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Please record your Time In before recording Time Out.',
                    ]);
                }

                if ($attendance->time_out) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Time Out has already been recorded for today.',
                    ]);
                }

                $storedPath = $photo->store('attendance-proofs/time-out', 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('Time Out proof photo storage failed.');
                }

                $attendance->time_out = $now;
                $attendance->time_out_photo_path = $storedPath;
                $attendance->time_out_verification_method = $usedMethod;
                $attendance->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Time Out recorded successfully.',
                    'attendance' => $this->attendancePayload($attendance->fresh()),
                ]);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null && !Storage::disk('local')->delete($storedPath)) {
                report(new RuntimeException('Failed to clean up a Time Out photo after a database error.'));
            }

            throw $exception;
        }
    }

    private function verifyBiometrics(array $validated, Employee $employee, Carbon $now): string
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

        // If config strictly requires face recognition, ensure face token is present
        if (config('face-recognition.require_for_attendance', false) && !$faceToken) {
            throw ValidationException::withMessages([
                'face_verification_token' => 'Face verification is required before attendance submission.',
            ]);
        }

        // Determine which method to use:
        // - Explicit method if supplied
        // - Otherwise, face if faceToken is provided and fingerprintToken is not
        // - Otherwise, fingerprint
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

        // Fingerprint method
        if (!$fingerprintToken) {
            throw ValidationException::withMessages([
                'biometric_verification_token' => 'Biometric verification token is required when fingerprint is selected.',
            ]);
        }

        $fingerprintVerification = BiometricVerification::query()
            ->where('token_hash', hash('sha256', $fingerprintToken))
            ->where('employee_id', $employee->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->lockForUpdate()
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
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $today = Carbon::today('Asia/Manila');
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        return response()->json([
            'success' => true,
            'attendance' => $this->attendancePayload($attendance),
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
            'status' => ['nullable', 'in:present,late,absent,on_leave'],
            'range' => ['nullable', 'in:this_week,this_month,all'],
        ]);

        $range = $validated['range'] ?? null;
        $now = Carbon::now('Asia/Manila');

        if ($range === 'this_week') {
            $fromDate = $now->copy()->startOfWeek()->format('Y-m-d');
            $toDate = $now->copy()->endOfWeek()->format('Y-m-d');
        } elseif ($range === 'this_month') {
            $fromDate = $now->copy()->startOfMonth()->format('Y-m-d');
            $toDate = $now->copy()->endOfMonth()->format('Y-m-d');
        } else {
            $fromDate = $validated['from_date'] ?? null;
            $toDate = $validated['to_date'] ?? null;
        }

        if ($fromDate && $toDate && $fromDate > $toDate) {
            throw ValidationException::withMessages([
                'from_date' => 'The start date must be on or before the end date.',
            ]);
        }

        $query = Attendance::query()
            ->where('employee_id', $employee->id);

        if ($fromDate && $toDate) {
            $query->whereBetween('attendance_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $query->whereDate('attendance_date', '>=', $fromDate);
        } elseif ($toDate) {
            $query->whereDate('attendance_date', '<=', $toDate);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $records = $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('time_in')
            ->get();

        $summaryQuery = Attendance::query()
            ->where('employee_id', $employee->id);

        if ($fromDate && $toDate) {
            $summaryQuery->whereBetween('attendance_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $summaryQuery->whereDate('attendance_date', '>=', $fromDate);
        } elseif ($toDate) {
            $summaryQuery->whereDate('attendance_date', '<=', $toDate);
        }

        $presentCount = (clone $summaryQuery)->where('status', 'present')->count();
        $lateCount = (clone $summaryQuery)->where('status', 'late')->count();
        $absentCount = (clone $summaryQuery)->where('status', 'absent')->count();
        $onLeaveCount = (clone $summaryQuery)->where('status', 'on_leave')->count();
        $totalRecords = $presentCount + $lateCount + $absentCount + $onLeaveCount;

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
                'on_leave' => $onLeaveCount,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    private function attendancePayload(?Attendance $attendance): ?array
    {
        if (!$attendance) {
            return null;
        }

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
            'time_in' => $attendance->time_in?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'time_out' => $attendance->time_out?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'time_in_verification_method' => $attendance->time_in_verification_method,
            'time_out_verification_method' => $attendance->time_out_verification_method,
            'timezone' => 'Asia/Manila',
            'status' => $attendance->status,
            'photo_url' => $attendance->photo_path
                ? route('api.attendance.photo', ['attendance' => $attendance->id], false)
                : null,
            'time_out_photo_url' => $attendance->time_out_photo_path
                ? route('api.attendance.time-out-photo', ['attendance' => $attendance->id], false)
                : null,
            'late_starting_time_snapshot' => $attendance->late_starting_time_snapshot,
            'created_at' => $attendance->created_at?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
            'updated_at' => $attendance->updated_at?->copy()->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'),
        ];
    }

    public function photo(Attendance $attendance)
    {
        return $this->attendancePhotoResponse($attendance, $attendance->photo_path);
    }

    public function timeOutPhoto(Attendance $attendance)
    {
        return $this->attendancePhotoResponse($attendance, $attendance->time_out_photo_path);
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
