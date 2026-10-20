@extends('layouts.admin')

@section('title', 'Approved Devices — ATTENDSYNC')
@section('page_title', 'Approved Devices')
@section('page_description', 'Manage and authorize employee mobile hardware for biometric attendance.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Manage and authorize employee mobile hardware for biometric attendance.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Devices</span>
                <span class="stat-icon blue" aria-hidden="true">
                    <x-lucide-smartphone class="w-5 h-5" />
                </span>
            </div>
            <div class="stat-number">{{ $stats['total'] }}</div>
            <div class="stat-note">Registered hardware profiles</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Approval</span>
                <span class="stat-icon orange" aria-hidden="true">
                    <x-lucide-clock class="w-5 h-5" />
                </span>
            </div>
            <div class="stat-number text-amber-700">{{ $stats['pending'] }}</div>
            <div class="stat-note">Awaiting admin review</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Approved</span>
                <span class="stat-icon green" aria-hidden="true">
                    <x-lucide-circle-check class="w-5 h-5" />
                </span>
            </div>
            <div class="stat-number text-emerald-700">{{ $stats['approved'] }}</div>
            <div class="stat-note">Active permitted devices</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Rejected</span>
                <span class="stat-icon red" aria-hidden="true">
                    <x-lucide-circle-x class="w-5 h-5" />
                </span>
            </div>
            <div class="stat-number text-rose-700">{{ $stats['rejected'] ?? 0 }}</div>
            <div class="stat-note">Denied registration</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Revoked</span>
                <span class="stat-icon red" aria-hidden="true">
                    <x-lucide-shield-ban class="w-5 h-5" />
                </span>
            </div>
            <div class="stat-number text-slate-700">{{ $stats['revoked'] }}</div>
            <div class="stat-note">Access blocked</div>
        </div>
    </div>

    <div class="employee-panel report-filter-panel">
        <div class="filter-pills-row">
            <span class="filter-pills-label">
                <x-lucide-list-filter class="w-4 h-4" aria-hidden="true" />
                <span>Filter Status:</span>
            </span>
            <div class="filter-pills-list">
                <a href="{{ route('admin.devices.index') }}" class="filter-pill {{ empty($status) ? 'active' : '' }}">
                    All
                </a>
                <a href="{{ route('admin.devices.index', ['status' => 'pending']) }}" class="filter-pill {{ $status === 'pending' ? 'active' : '' }}">
                    Pending ({{ $stats['pending'] }})
                </a>
                <a href="{{ route('admin.devices.index', ['status' => 'approved']) }}" class="filter-pill {{ $status === 'approved' ? 'active' : '' }}">
                    Approved ({{ $stats['approved'] }})
                </a>
                <a href="{{ route('admin.devices.index', ['status' => 'rejected']) }}" class="filter-pill {{ $status === 'rejected' ? 'active' : '' }}">
                    Rejected ({{ $stats['rejected'] ?? 0 }})
                </a>
                <a href="{{ route('admin.devices.index', ['status' => 'revoked']) }}" class="filter-pill {{ $status === 'revoked' ? 'active' : '' }}">
                    Revoked ({{ $stats['revoked'] }})
                </a>
            </div>
        </div>
    </div>

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Registered Mobile Devices ({{ $devices->total() }})</h3>
                <p>Hardware UUIDs and biometric verification status</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Device Model &amp; OS</th>
                        <th>Device Identifier (UUID)</th>
                        <th>Face Verified</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td>
                                <strong>{{ $device->employee?->full_name ?? '—' }}</strong>
                                <small class="table-meta-line">{{ $device->employee?->employee_id ?? '—' }}</small>
                            </td>
                            <td>
                                <strong>{{ $device->device_model ?: 'Android Device' }}</strong>
                                <small class="table-meta-line">{{ $device->device_os ?: 'OS Unspecified' }}</small>
                            </td>
                            <td>
                                <code class="font-mono text-xs bg-slate-100 text-slate-800 px-2 py-1 rounded border border-slate-200">{{ $device->device_uuid }}</code>
                            </td>
                            <td>
                                @if($device->verified_via_face)
                                    <span class="badge badge-success">
                                        <x-lucide-circle-check class="w-3 h-3" aria-hidden="true" />
                                        <span>Face Verified</span>
                                    </span>
                                @else
                                    <span class="badge badge-neutral">
                                        <span>Manual</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($device->status === 'approved')
                                    <span class="status-badge status-present">Approved</span>
                                @elseif($device->status === 'pending')
                                    <span class="status-badge status-late">Pending</span>
                                @elseif($device->status === 'rejected')
                                    <span class="status-badge status-absent">Rejected</span>
                                    @if($device->rejection_reason)
                                        <small class="table-meta-line text-rose-600">{{ Str::limit($device->rejection_reason, 30) }}</small>
                                    @endif
                                @else
                                    <span class="status-badge status-absent">Revoked</span>
                                    @if($device->rejection_reason)
                                        <small class="table-meta-line text-slate-500">{{ Str::limit($device->rejection_reason, 30) }}</small>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="text-xs text-secondary">
                                    {{ $device->registered_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="employee-actions">
                                    @if($device->status === 'pending')
                                        <form method="POST" action="{{ route('admin.devices.approve', $device) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="table-action edit-action" style="color: #15803D; border-color: #BBF7D0;">
                                                <x-lucide-circle-check class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Approve</span>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.devices.reject', $device) }}"
                                            style="display:inline;"
                                            data-confirm-message="Are you sure you want to reject this device registration request?"
                                            data-confirm-title="Reject Device Registration"
                                            data-confirm-type="danger"
                                            data-confirm-btn="Reject"
                                        >
                                            @csrf
                                            <button type="submit" class="table-action delete-action">
                                                <x-lucide-circle-x class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Reject</span>
                                            </button>
                                        </form>
                                    @elseif($device->status === 'approved')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.devices.revoke', $device) }}"
                                            style="display:inline;"
                                            data-confirm-message="Are you sure you want to revoke this device? The employee will no longer be able to submit attendance from it."
                                            data-confirm-title="Revoke Device"
                                            data-confirm-type="danger"
                                            data-confirm-btn="Revoke"
                                        >
                                            @csrf
                                            <button type="submit" class="table-action delete-action">
                                                <x-lucide-circle-x class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Revoke</span>
                                            </button>
                                        </form>
                                    @elseif($device->status === 'rejected')
                                        <form method="POST" action="{{ route('admin.devices.approve', $device) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="table-action edit-action">
                                                <x-lucide-circle-check class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Approve</span>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.devices.approve', $device) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="table-action edit-action">
                                                <x-lucide-circle-check class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Re-Approve</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-smartphone class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No device registrations found</h4>
                                <p>Devices will appear here as employees install the mobile app and register hardware.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="request-pagination">
            {{ $devices->links() }}
        </div>
    </div>
</section>
@endsection
