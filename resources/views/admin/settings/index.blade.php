@extends('layouts.admin')

@section('title', 'Settings | ATTENDSYNC')
@section('page_title', 'Settings')
@section('page_description', 'Manage ATTENDSYNC network restrictions and attendance settings.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">
                Manage network restriction boundaries and attendance threshold configurations.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <x-lucide-triangle-alert class="alert-icon" aria-hidden="true" />
            <div>
                <strong>Please resolve the following errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="employee-panel" id="network-settings">
        <div class="employee-panel-header">
            <div>
                <h3>Network Restriction Settings</h3>
                <p>
                    Restrict mobile app attendance and protected features to the authorized school Wi-Fi network.
                </p>
            </div>
            <div class="badge-group">
                <span class="status-badge {{ $networkSetting->is_enabled ? 'status-present' : 'status-absent' }}">
                    {{ $networkSetting->is_enabled ? 'Restriction Enabled' : 'Restriction Disabled' }}
                </span>
                <span class="status-badge {{ $networkStatusInfo['status'] === 'verified' ? 'status-present' : ($networkStatusInfo['status'] === 'unverified' ? 'status-late' : ($networkStatusInfo['status'] === 'misconfigured' ? 'status-absent' : 'status-late')) }}">
                    Verification: {{ ucfirst($networkStatusInfo['status']) }}
                </span>
            </div>
        </div>

        @if ($networkSetting->is_enabled && $networkStatusInfo['warning'])
            <div class="alert alert-warning">
                <x-lucide-triangle-alert class="alert-icon" aria-hidden="true" />
                <div>
                    <strong>Security Warning:</strong> {{ $networkStatusInfo['warning'] }}
                </div>
            </div>
        @endif

        <div class="settings-overview-grid">
            <div class="settings-overview-item">
                <span class="settings-overview-label">Authorized SSID</span>
                <div class="settings-overview-val">
                    {{ $networkSetting->authorized_ssid ?: 'Not configured' }}
                </div>
                <small class="table-meta-line">{{ $networkSetting->network_description ?: 'No description' }}</small>
            </div>

            <div class="settings-overview-item">
                <span class="settings-overview-label">Verification Method</span>
                <div class="settings-overview-val">
                    @if ($networkSetting->verification_method === 'client_assisted')
                        Client-Assisted Wi-Fi Matching
                    @elseif ($networkSetting->verification_method === 'trusted_ip_range')
                        Server-Verified School IP Range
                    @elseif ($networkSetting->verification_method === 'school_vpn_or_gateway')
                        School Gateway / VPN Boundary
                    @else
                        {{ ucfirst($networkSetting->verification_method) }}
                    @endif
                </div>
                <small class="table-meta-line">{{ $networkStatusInfo['description'] }}</small>
            </div>

            <div class="settings-overview-item">
                <span class="settings-overview-label">Authorized BSSIDs</span>
                <div class="settings-overview-val">
                    {{ count($networkSetting->authorized_bssid_list) }} Access Point(s)
                </div>
                <small class="table-meta-line">
                    {{ count($networkSetting->authorized_bssid_list) > 0 ? implode(', ', array_slice($networkSetting->authorized_bssid_list, 0, 2)) . (count($networkSetting->authorized_bssid_list) > 2 ? '...' : '') : 'Any BSSID permitted under SSID' }}
                </small>
            </div>

            <div class="settings-overview-item">
                <span class="settings-overview-label">Last Modified</span>
                <div class="settings-overview-val">
                    {{ $networkSetting->updater?->name ?? 'System' }}
                </div>
                <small class="table-meta-line">{{ $networkSetting->updated_at ? $networkSetting->updated_at->format('M d, Y h:i A') : 'Default policy' }}</small>
            </div>
        </div>

        <hr class="panel-divider">

        <div class="settings-form">
            <h4>Update Network Policy</h4>
            <p class="text-secondary text-sm mb-4">
                Changes take effect immediately across all employee mobile devices without rebuilding the app.
            </p>

            <form method="POST" action="{{ route('admin.settings.network.update') }}" class="panel-form">
                @csrf

                <div class="form-group">
                    <label class="checkbox-label font-bold text-sm">
                        <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $networkSetting->is_enabled) ? 'checked' : '' }}>
                        <span>Enable School Network Restriction</span>
                    </label>
                    <small class="form-help-text">
                        When checked, protected mobile screens (Home, Attendance, Records, Corrections, Profile) and APIs are blocked unless the employee is connected to the authorized school network.
                    </small>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="authorized_ssid">Authorized Wi-Fi SSID</label>
                        <input
                            type="text"
                            id="authorized_ssid"
                            name="authorized_ssid"
                            value="{{ old('authorized_ssid', $networkSetting->authorized_ssid) }}"
                            placeholder="e.g. BansudCS-Faculty"
                        >
                        <small class="form-help-text">
                            The exact Wi-Fi network name broadcast by the school router/access point.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="network_description">Network Label / Description</label>
                        <input
                            type="text"
                            id="network_description"
                            name="network_description"
                            value="{{ old('network_description', $networkSetting->network_description) }}"
                            placeholder="e.g. Bansud Central School Main Wi-Fi"
                        >
                        <small class="form-help-text">
                            Displayed to employees on the mobile Network Lock screen and status banner.
                        </small>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="verification_method">Verification Method</label>
                        <select id="verification_method" name="verification_method" onchange="toggleIpRangesField()">
                            <option value="client_assisted" {{ old('verification_method', $networkSetting->verification_method) === 'client_assisted' ? 'selected' : '' }}>
                                Client-Assisted Wi-Fi Matching (SSID / BSSID)
                            </option>
                            <option value="trusted_ip_range" {{ old('verification_method', $networkSetting->verification_method) === 'trusted_ip_range' ? 'selected' : '' }}>
                                Server-Verified School Gateway IP / Subnet CIDR (Strong)
                            </option>
                            <option value="school_vpn_or_gateway" {{ old('verification_method', $networkSetting->verification_method) === 'school_vpn_or_gateway' ? 'selected' : '' }}>
                                School-Controlled VPN / Dedicated Gateway (Strong)
                            </option>
                        </select>
                        <small class="form-help-text">
                            Select how Laravel authenticates the connection path.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="authorized_bssids">Authorized AP BSSIDs (Optional)</label>
                        <input
                            type="text"
                            id="authorized_bssids"
                            name="authorized_bssids"
                            value="{{ old('authorized_bssids', $networkSetting->authorized_bssids) }}"
                            placeholder="e.g. AA:BB:CC:DD:EE:FF, 11:22:33:44:55:66"
                        >
                        <small class="form-help-text">
                            Comma-separated or newline-separated access point MAC addresses. Leave blank if multiple mesh APs are used.
                        </small>
                    </div>
                </div>

                <div class="form-group" id="trusted_ip_group" style="{{ in_array(old('verification_method', $networkSetting->verification_method), ['trusted_ip_range', 'school_vpn_or_gateway']) ? '' : 'display: none;' }}">
                    <label for="trusted_ip_ranges">Trusted Gateway IPs, VPN Subnets, or CIDR Ranges</label>
                    <textarea
                        id="trusted_ip_ranges"
                        name="trusted_ip_ranges"
                        rows="3"
                        class="font-mono text-xs"
                        placeholder="e.g. 192.168.1.0/24&#10;10.8.0.0/24&#10;120.28.100.5"
                    >{{ old('trusted_ip_ranges', $networkSetting->trusted_ip_ranges) }}</textarea>
                    <small class="form-help-text">
                        Enter one or more IP addresses or CIDR blocks (e.g. school static WAN IP, local faculty subnet, or school VPN range). Laravel will verify requests against these ranges.
                    </small>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn-primary">
                        <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                        <span>Save Network Restriction Settings</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleIpRangesField() {
            var select = document.getElementById('verification_method');
            var group = document.getElementById('trusted_ip_group');
            if (select && group) {
                if (select.value === 'trusted_ip_range' || select.value === 'school_vpn_or_gateway') {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            }
        }
    </script>

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Attendance Fallback Settings</h3>
                <p>Configure fallback late threshold for employees without custom schedules.</p>
            </div>
        </div>

        <div class="settings-current">
            <h4>Current Late Starting Time</h4>
            @if ($currentSetting)
                <div class="current-setting-time">
                    {{ \Carbon\Carbon::parse($currentSetting->late_starting_time)->format('h:i A') }}
                </div>
                <p class="text-secondary text-xs">
                    Effective since: {{ $currentSetting->effective_date->format('F d, Y') }}
                </p>
            @else
                <div class="current-setting-time text-slate-400">
                    Not configured
                </div>
                <p class="text-secondary text-xs">
                    No default fallback schedule configured yet.
                </p>
            @endif
        </div>

        <hr class="panel-divider">

        <div class="settings-form">
            <h4>Add New Attendance Schedule Fallback</h4>
            <p class="text-secondary text-sm mb-4">
                Create a new schedule without changing previous historical attendance records.
            </p>

            <form
                method="POST"
                action="{{ route('admin.settings.store') }}"
                class="panel-form"
            >
                @csrf

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="late_starting_time">Late Starting Time *</label>
                        <input
                            type="time"
                            id="late_starting_time"
                            name="late_starting_time"
                            value="{{ old('late_starting_time', '08:00') }}"
                            required
                        >
                        <small class="form-help-text">
                            Employees who time in after this time will be marked as late.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="effective_date">Effective Date *</label>
                        <input
                            type="date"
                            id="effective_date"
                            name="effective_date"
                            value="{{ old('effective_date', now()->format('Y-m-d')) }}"
                            required
                        >
                        <small class="form-help-text">
                            The new schedule will apply starting on this date.
                        </small>
                    </div>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn-primary">
                        <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                        <span>Save Attendance Schedule</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Schedule History</h3>
                <p>Previously saved attendance schedules and cutoff records.</p>
            </div>
        </div>

        @if ($settings->isEmpty())
            <div class="empty-employees">
                <div class="empty-icon" aria-hidden="true">
                    <x-lucide-calendar class="w-8 h-8 text-slate-400" />
                </div>
                <h4>No Schedule History</h4>
                <p>No attendance schedules have been saved yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Effective Date</th>
                            <th>Late Starting Time</th>
                            <th>Created By</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settings as $setting)
                            <tr>
                                <td>
                                    <strong>{{ $setting->effective_date->format('F d, Y') }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-warning">
                                        <x-lucide-clock class="w-3 h-3" aria-hidden="true" />
                                        <span>{{ \Carbon\Carbon::parse($setting->late_starting_time)->format('h:i A') }}</span>
                                    </span>
                                </td>
                                <td>
                                    {{ $setting->creator?->name ?? 'System' }}
                                </td>
                                <td>
                                    <span class="text-xs text-secondary">
                                        {{ $setting->created_at->format('M d, Y h:i A') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection