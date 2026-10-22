<?php

namespace App\Services;

use App\Models\ApprovedDevice;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceAuthorizationService
{
    public function __construct(protected DeviceProofService $deviceProofService)
    {
    }

    /**
     * Validate that the requesting device is authorized for attendance submission.
     *
     * @throws ValidationException
     */
    public function validateDevice(Request $request, Employee $employee): ?ApprovedDevice
    {
        $deviceId = $request->header('X-Device-Id')
            ?? $request->input('device_id')
            ?? $request->input('device_uuid');

        $requireApproval = config('devices.require_device_approval', true);

        // If a device identifier is explicitly supplied, strictly validate it
        if ($deviceId) {
            $device = ApprovedDevice::where('employee_id', $employee->id)
                ->where('device_uuid', $deviceId)
                ->first();

            if (!$device) {
                throw ValidationException::withMessages([
                    'device' => 'This device is not registered. Please complete face verification to register this device for approval.',
                ]);
            }

            if ($device->status === 'pending') {
                throw ValidationException::withMessages([
                    'device' => 'Device registration is pending administrator approval.',
                ]);
            }

            if ($device->status === 'rejected') {
                $reasonMsg = $device->rejection_reason ? " Reason: {$device->rejection_reason}" : '';
                throw ValidationException::withMessages([
                    'device' => "This device registration has been rejected by the administrator.{$reasonMsg}",
                ]);
            }

            if ($device->status === 'revoked') {
                throw ValidationException::withMessages([
                    'device' => 'This device has been revoked by the administrator.',
                ]);
            }

            if (!$device->public_key) {
                throw ValidationException::withMessages([
                    'device' => 'This device must be registered again to establish secure device identity.',
                ]);
            }
            $this->deviceProofService->assertValid($request, $device->public_key);

            return $device;
        }

        // If no device ID was provided, but device approval is required and employee has registered devices
        if ($requireApproval && ApprovedDevice::where('employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages([
                'device' => 'Device authorization is required. Please provide an approved device ID.',
            ]);
        }

        return null;
    }
}
