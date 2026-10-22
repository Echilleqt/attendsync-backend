<?php

namespace App\Http\Controllers;

use App\Models\ApprovedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDeviceController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,revoked'],
        ]);
        $status = $validated['status'] ?? null;

        $query = ApprovedDevice::with(['employee.user', 'approver'])
            ->latest('registered_at');

        if ($status && in_array($status, ['pending', 'approved', 'rejected', 'revoked'], true)) {
            $query->where('status', $status);
        }

        $devices = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => ApprovedDevice::count(),
            'pending' => ApprovedDevice::where('status', 'pending')->count(),
            'approved' => ApprovedDevice::where('status', 'approved')->count(),
            'rejected' => ApprovedDevice::where('status', 'rejected')->count(),
            'revoked' => ApprovedDevice::where('status', 'revoked')->count(),
        ];

        return view('admin.devices.index', compact('devices', 'stats', 'status'));
    }

    public function approve(ApprovedDevice $device)
    {
        $device->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now('Asia/Manila'),
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', "Device {$device->device_model} ({$device->device_uuid}) approved successfully.");
    }

    public function reject(Request $request, ApprovedDevice $device)
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $device->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now('Asia/Manila'),
            'rejection_reason' => $validated['rejection_reason'] ?? 'Registration rejected by administrator',
        ]);

        return redirect()->back()->with('success', "Device {$device->device_model} ({$device->device_uuid}) has been rejected.");
    }

    public function revoke(Request $request, ApprovedDevice $device)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $reason = $validated['reason'] ?? 'Revoked by administrator';

        $device->update([
            'status' => 'revoked',
            'rejection_reason' => $reason,
        ]);

        return redirect()->back()->with('success', "Device {$device->device_model} ({$device->device_uuid}) has been revoked.");
    }
}
