
@extends('layouts.admin')

@section('title', 'Settings | ATTENDSYNC')

@section('page_title', 'Settings')

@section('page_description', 'Manage ATTENDSYNC system settings.')

@section('content')

<section class="employee-page">

    <div class="employee-heading">
        <div>
            <span class="school-label">
                BANSUD CENTRAL SCHOOL
            </span>

            <p class="employee-context">
                Manage attendance schedule settings.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="employee-panel" id="network-settings" style="margin-bottom: 28px;">

        <div class="employee-panel-header">
            <div>
                <h3>Network Restriction Settings</h3>
                <p>
                    Restrict mobile app attendance and protected features to the authorized school Wi-Fi network.
                </p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span class="status-badge {{ $networkSetting->is_enabled ? 'status-present' : 'status-absent' }}">
                    {{ $networkSetting->is_enabled ? 'Restriction Enabled' : 'Restriction Disabled' }}
                </span>
                <span class="status-badge {{ $networkStatusInfo['status'] === 'verified' ? 'status-present' : ($networkStatusInfo['status'] === 'unverified' ? 'status-late' : ($networkStatusInfo['status'] === 'misconfigured' ? 'status-absent' : 'status-late')) }}">
                    Verification: {{ ucfirst($networkStatusInfo['status']) }}
                </span>
            </div>
        </div>

        @if ($networkSetting->is_enabled && $networkStatusInfo['warning'])
            <div class="alert" style="background: #fff8e6; border: 1px solid #ffd591; color: #ad6800; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px;">
                <strong>Security Warning:</strong> {{ $networkStatusInfo['warning'] }}
            </div>
        @endif

        <div class="settings-current" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; padding: 18px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Authorized SSID</span>
                <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 4px;">
                    {{ $networkSetting->authorized_ssid ?: 'Not configured' }}
                </div>
                <small style="color: #64748b;">{{ $networkSetting->network_description ?: 'No description' }}</small>
            </div>

            <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Verification Method</span>
                <div style="font-size: 15px; font-weight: 600; color: #1e293b; margin-top: 4px;">
                    @if ($networkSetting->verification_method === 'client_assisted')
                        Client-Assisted Wi-Fi Matching
                    @elseif ($networkSetting->verification_method === 'trusted_ip_range')
                        Server-Verified IP/CIDR Range
                    @elseif ($networkSetting->verification_method === 'school_vpn_or_gateway')
                        School Gateway / VPN Boundary
                    @else
                        {{ ucfirst($networkSetting->verification_method) }}
                    @endif
                </div>
                <small style="color: #64748b;">{{ $networkStatusInfo['description'] }}</small>
            </div>

            <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Authorized BSSIDs</span>
                <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 4px;">
                    {{ count($networkSetting->authorized_bssid_list) }} Access Point(s)
                </div>
                <small style="color: #64748b;">
                    {{ count($networkSetting->authorized_bssid_list) > 0 ? implode(', ', array_slice($networkSetting->authorized_bssid_list, 0, 2)) . (count($networkSetting->authorized_bssid_list) > 2 ? '...' : '') : 'Any BSSID permitted under SSID' }}
                </small>
            </div>

            <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Last Modified</span>
                <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 4px;">
                    {{ $networkSetting->updater?->name ?? 'System' }}
                </div>
                <small style="color: #64748b;">{{ $networkSetting->updated_at ? $networkSetting->updated_at->format('M d, Y h:i A') : 'Default policy' }}</small>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #edf0f6; margin: 24px 0;">

        <div class="settings-form">
            <h4>Update Network Policy</h4>
            <p style="color: #64748b; margin-bottom: 20px;">
                Changes take effect immediately across all employee mobile devices without rebuilding the app.
            </p>

            <form method="POST" action="{{ route('admin.settings.network.update') }}">
                @csrf

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 15px; font-weight: 700;">
                        <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $networkSetting->is_enabled) ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
                        Enable School Network Restriction
                    </label>
                    <small style="color: #64748b; margin-left: 28px;">
                        When checked, protected mobile screens (Home, Attendance, Records, Corrections, Profile) and APIs are blocked unless the employee is connected to the authorized school network.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="authorized_ssid">Authorized Wi-Fi SSID</label>
                        <input
                            type="text"
                            id="authorized_ssid"
                            name="authorized_ssid"
                            value="{{ old('authorized_ssid', $networkSetting->authorized_ssid) }}"
                            placeholder="e.g. BansudCS-Faculty"
                        >
                        <small style="color: #64748b;">
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
                        <small style="color: #64748b;">
                            Displayed to employees on the mobile Network Lock screen and status banner.
                        </small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 18px;">
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
                        <small style="color: #64748b;">
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
                        <small style="color: #64748b;">
                            Comma-separated or newline-separated access point MAC addresses. Leave blank if multiple mesh APs are used.
                        </small>
                    </div>
                </div>

                <div class="form-group" id="trusted_ip_group" style="margin-top: 18px; {{ in_array(old('verification_method', $networkSetting->verification_method), ['trusted_ip_range', 'school_vpn_or_gateway']) ? '' : 'display: none;' }}">
                    <label for="trusted_ip_ranges">Trusted Gateway IPs, VPN Subnets, or CIDR Ranges</label>
                    <textarea
                        id="trusted_ip_ranges"
                        name="trusted_ip_ranges"
                        rows="3"
                        style="width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #d9dee7; border-radius: 8px; font-family: monospace; font-size: 13px;"
                        placeholder="e.g. 192.168.1.0/24&#10;10.8.0.0/24&#10;120.28.100.5"
                    >{{ old('trusted_ip_ranges', $networkSetting->trusted_ip_ranges) }}</textarea>
                    <small style="color: #64748b;">
                        Enter one or more IP addresses or CIDR blocks (e.g. school static WAN IP, local faculty subnet, or school VPN range). Laravel will verify requests against these ranges.
                    </small>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn-primary">
                        Save Network Restriction Settings
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
                <h3>Attendance Settings</h3>

                <p>
                    Set the time when an employee will be marked as late.
                </p>
            </div>
        </div>

        <div class="settings-current">

            <h4>Current Late Starting Time</h4>

            @if ($currentSetting)
                <div class="current-setting-time">
                    {{ \Carbon\Carbon::parse($currentSetting->late_starting_time)->format('h:i A') }}
                </div>

                <p>
                    Effective since:
                    {{ $currentSetting->effective_date->format('F d, Y') }}
                </p>
            @else
                <div class="current-setting-time">
                    Not configured
                </div>

                <p>
                    No attendance schedule has been configured yet.
                </p>
            @endif

        </div>

        <hr>

        <div class="settings-form">

            <h4>Add New Attendance Schedule</h4>

            <p>
                Create a new schedule without changing previous attendance
                records.
            </p>

            <form
                method="POST"
                action="{{ route('admin.settings.store') }}"
            >
                @csrf

                <div class="form-group">
                    <label for="late_starting_time">
                        Late Starting Time
                    </label>

                    <input
                        type="time"
                        id="late_starting_time"
                        name="late_starting_time"
                        value="{{ old('late_starting_time', '08:00') }}"
                        required
                    >

                    <small>
                        Employees who time in after this time will be marked
                        as late.
                    </small>
                </div>

                <div class="form-group">
                    <label for="effective_date">
                        Effective Date
                    </label>

                    <input
                        type="date"
                        id="effective_date"
                        name="effective_date"
                        value="{{ old('effective_date', now()->format('Y-m-d')) }}"
                        required
                    >

                    <small>
                        The new schedule will apply starting on this date.
                    </small>
                </div>

                <button type="submit" class="btn-primary">
                    Save Attendance Schedule
                </button>

            </form>

        </div>

    </div>

    <div class="employee-panel">

        <div class="employee-panel-header">
            <div>
                <h3>Schedule History</h3>

                <p>
                    Previously saved attendance schedules.
                </p>
            </div>
        </div>

        @if ($settings->isEmpty())

            <div class="empty-employees">
                <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="m19.3 13.7 1.1.9-1.5 2.6-1.4-.5a7.6 7.6 0 0 1-1.6.9l-.3 1.5h-3l-.3-1.5a7.6 7.6 0 0 1-1.6-.9l-1.4.5-1.5-2.6 1.1-.9a7.2 7.2 0 0 1 0-1.8l-1.1-.9 1.5-2.6 1.4.5a7.6 7.6 0 0 1 1.6-.9l.3-1.5h3l.3 1.5a7.6 7.6 0 0 1 1.6.9l1.4-.5 1.5 2.6-1.1.9a7.2 7.2 0 0 1 0 1.8Z"/></svg></div>

                <h4>No Schedule History</h4>

                <p>
                    No attendance schedules have been saved yet.
                </p>
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
                                    {{ $setting->effective_date->format('F d, Y') }}
                                </td>

                                <td>
                                    {{ \Carbon\Carbon::parse($setting->late_starting_time)->format('h:i A') }}
                                </td>

                                <td>
                                    {{ $setting->creator?->name ?? 'System' }}
                                </td>

                                <td>
                                    {{ $setting->created_at->format('M d, Y h:i A') }}
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