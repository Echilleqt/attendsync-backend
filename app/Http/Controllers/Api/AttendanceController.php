<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\BiometricVerification;
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
            'biometric_verification_token' => ['required', 'string', 'size:64'],
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
                $verification = BiometricVerification::query()
                    ->where('token_hash', hash('sha256', $validated['biometric_verification_token']))
                    ->where('employee_id', $employee->id)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if (!$verification) {
                    throw ValidationException::withMessages([
                        'biometric_verification_token' => 'Biometric verification is invalid, expired, or already used.',
                    ]);
                }

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
                $attendance->time_in = $now->copy()->setTimezone(config('app.timezone'));
                $attendance->status = $threshold !== null && $now->format('H:i:s') > $threshold
                    ? 'late'
                    : 'present';
                $attendance->late_starting_time_snapshot = $threshold;

                $storedPath = $photo->store('attendance-proofs', 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('Attendance photo storage failed.');
                }

                $attendance->photo_path = $storedPath;
                $attendance->save();

                $verification->used_at = $now;
                $verification->save();

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
            'biometric_verification_token' => ['required', 'string', 'size:64'],
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
                $verification = BiometricVerification::query()
                    ->where('token_hash', hash('sha256', $validated['biometric_verification_token']))
                    ->where('employee_id', $employee->id)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if (!$verification) {
                    throw ValidationException::withMessages([
                        'biometric_verification_token' => 'Biometric verification is invalid, expired, or already used.',
                    ]);
                }

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

                $attendance->time_out = $now->copy()->setTimezone(config('app.timezone'));
                $attendance->time_out_photo_path = $storedPath;
                $attendance->save();

                $verification->used_at = $now;
                $verification->save();

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
