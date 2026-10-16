<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovedDevice;
use App\Models\FaceVerificationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceRegistrationController extends Controller
{
    /**
     * Register a new or existing device for an employee, requiring face verification.
     * The employee's existing face template is reused and preserved.
     */
    public function register(Request $request): JsonResponse
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
            'device_uuid' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'device_os' => ['nullable', 'string', 'max:191'],
            'face_verification_token' => ['required', 'string', 'size:64'],
        ]);

        // Verify the single-use face verification token against the employee's existing face template
        $tokenRecord = FaceVerificationToken::query()
            ->where('token_hash', hash('sha256', $validated['face_verification_token']))
            ->where('employee_id', $employee->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            throw ValidationException::withMessages([
                'face_verification_token' => 'Face verification is invalid, expired, or already used. Please verify face again to register this device.',
            ]);
        }

        // Mark face verification token as consumed
        $tokenRecord->update([
            'used_at' => now('Asia/Manila'),
        ]);

        // Register or update device request without touching or duplicating the existing face template
        $device = ApprovedDevice::withTrashed()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'device_uuid' => $validated['device_uuid'],
            ],
            [
                'device_name' => $validated['device_name'] ?? null,
                'device_model' => $validated['device_model'] ?? null,
                'device_os' => $validated['device_os'] ?? null,
                'status' => 'pending',
                'verified_via_face' => true,
                'registered_at' => now('Asia/Manila'),
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'deleted_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registration request submitted successfully. Awaiting administrator approval.',
            'device' => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'device_model' => $device->device_model,
                'status' => $device->status,
                'verified_via_face' => (bool) $device->verified_via_face,
                'registered_at' => $device->registered_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get the authorization status of a device for the authenticated employee.
     */
    public function status(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee') {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $deviceUuid = $request->query('device_uuid') ?? $request->header('X-Device-Id');

        if (!$deviceUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Device UUID is required.',
            ], 422);
        }

        $device = ApprovedDevice::where('employee_id', $employee->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => true,
                'registered' => false,
                'status' => 'unregistered',
                'message' => 'This device is not registered.',
            ]);
        }

        return response()->json([
            'success' => true,
            'registered' => true,
            'status' => $device->status,
            'verified_via_face' => (bool) $device->verified_via_face,
            'approved_at' => $device->approved_at?->toIso8601String(),
            'device' => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'device_name' => $device->device_name,
                'device_model' => $device->device_model,
                'status' => $device->status,
            ],
        ]);
    }
}
