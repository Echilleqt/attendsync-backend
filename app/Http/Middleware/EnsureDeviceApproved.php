<?php

namespace App\Http\Middleware;

use App\Models\ApprovedDevice;
use App\Services\DeviceAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceApproved
{
    public function __construct(protected DeviceAuthorizationService $deviceAuthorizationService)
    {
    }

    /**
     * Handle an incoming request, ensuring the employee device is approved.
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        // If not authenticated or not an employee, continue to next middleware
        if (!$user || $user->role !== 'employee') {
            return $next($request);
        }

        $employee = $user->employee;
        if (!$employee || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        // Strictly validate device approval status
        $device = $this->deviceAuthorizationService->validateDevice($request, $employee);

        if ($device && !$device->isApproved()) {
            throw ValidationException::withMessages([
                'device' => 'Device registration is not approved. Access to protected features is restricted.',
            ]);
        }

        $request->attributes->set('approved_device_id', $device?->id);

        return $next($request);
    }
}
