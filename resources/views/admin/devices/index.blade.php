@extends('layouts.admin')

@section('title', 'Approved Devices — ATTENDSYNC')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="page-title">Approved Devices</h1>
        <p class="page-subtitle">Manage and authorize employee mobile hardware for biometric attendance.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:20px;">
    <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
        <span style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Total Devices</span>
        <h2 style="font-size:24px; font-weight:700; margin:4px 0 0; color:#111827;">{{ $stats['total'] }}</h2>
    </div>
    <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
        <span style="font-size:12px; color:#d97706; font-weight:600; text-transform:uppercase;">Pending Approval</span>
        <h2 style="font-size:24px; font-weight:700; margin:4px 0 0; color:#d97706;">{{ $stats['pending'] }}</h2>
    </div>
    <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
        <span style="font-size:12px; color:#059669; font-weight:600; text-transform:uppercase;">Approved</span>
        <h2 style="font-size:24px; font-weight:700; margin:4px 0 0; color:#059669;">{{ $stats['approved'] }}</h2>
    </div>
    <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
        <span style="font-size:12px; color:#dc2626; font-weight:600; text-transform:uppercase;">Revoked</span>
        <h2 style="font-size:24px; font-weight:700; margin:4px 0 0; color:#dc2626;">{{ $stats['revoked'] }}</h2>
    </div>
</div>

<div class="filter-bar" style="background:#fff; padding:12px 16px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:16px; display:flex; gap:10px; align-items:center;">
    <span style="font-size:13px; font-weight:600; color:#4b5563;">Filter Status:</span>
    <a href="{{ route('admin.devices.index') }}" class="btn btn-sm" style="padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; {{ empty($status) ? 'background:#1f2937; color:#fff;' : 'background:#f3f4f6; color:#374151;' }}">All</a>
    <a href="{{ route('admin.devices.index', ['status' => 'pending']) }}" class="btn btn-sm" style="padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; {{ $status === 'pending' ? 'background:#d97706; color:#fff;' : 'background:#fef3c7; color:#92400e;' }}">Pending ({{ $stats['pending'] }})</a>
    <a href="{{ route('admin.devices.index', ['status' => 'approved']) }}" class="btn btn-sm" style="padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; {{ $status === 'approved' ? 'background:#059669; color:#fff;' : 'background:#d1fae5; color:#065f46;' }}">Approved ({{ $stats['approved'] }})</a>
    <a href="{{ route('admin.devices.index', ['status' => 'revoked']) }}" class="btn btn-sm" style="padding:6px 12px; border-radius:6px; font-size:12px; text-decoration:none; {{ $status === 'revoked' ? 'background:#dc2626; color:#fff;' : 'background:#fee2e2; color:#991b1b;' }}">Revoked ({{ $stats['revoked'] }})</a>
</div>

<div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; overflow:hidden;">
    <table class="table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
        <thead>
            <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb; color:#6b7280;">
                <th style="padding:12px 16px;">Employee</th>
                <th style="padding:12px 16px;">Device Model &amp; OS</th>
                <th style="padding:12px 16px;">Device Identifier (UUID)</th>
                <th style="padding:12px 16px;">Face Verified</th>
                <th style="padding:12px 16px;">Status</th>
                <th style="padding:12px 16px;">Registered</th>
                <th style="padding:12px 16px; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($devices as $device)
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:12px 16px;">
                    <strong>{{ $device->employee?->full_name ?? '—' }}</strong><br>
                    <small style="color:#6b7280;">{{ $device->employee?->employee_id ?? '—' }}</small>
                </td>
                <td style="padding:12px 16px;">
                    <span>{{ $device->device_model ?: 'Android Device' }}</span><br>
                    <small style="color:#6b7280;">{{ $device->device_os ?: 'OS Unspecified' }}</small>
                </td>
                <td style="padding:12px 16px; font-family:monospace; color:#374151;">
                    {{ $device->device_uuid }}
                </td>
                <td style="padding:12px 16px;">
                    @if($device->verified_via_face)
                        <span style="background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600;">Live Face Verified</span>
                    @else
                        <span style="background:#f3f4f6; color:#6b7280; padding:2px 8px; border-radius:999px; font-size:11px;">Manual</span>
                    @endif
                </td>
                <td style="padding:12px 16px;">
                    @if($device->status === 'approved')
                        <span style="background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600;">Approved</span>
                    @elseif($device->status === 'pending')
                        <span style="background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600;">Pending Approval</span>
                    @else
                        <span style="background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600;">Revoked</span>
                    @endif
                </td>
                <td style="padding:12px 16px; color:#6b7280;">
                    {{ $device->registered_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}
                </td>
                <td style="padding:12px 16px; text-align:right;">
                    @if($device->status === 'pending')
                        <form method="POST" action="{{ route('admin.devices.approve', $device) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background:#059669; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600;">Approve</button>
                        </form>
                    @endif

                    @if($device->status !== 'revoked')
                        <form method="POST" action="{{ route('admin.devices.revoke', $device) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to revoke this device? The employee will no longer be able to submit attendance from it.');">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">Revoke</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.devices.approve', $device) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background:#2563eb; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">Re-Approve</button>
                        </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding:32px 16px; text-align:center; color:#9ca3af;">
                    No device registration requests found matching criteria.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div style="padding:12px 16px;">
        {{ $devices->links() }}
    </div>
</div>
@endsection
