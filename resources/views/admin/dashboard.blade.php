@extends('layouts.admin')

@section('title', 'Admin Dashboard | ATTENDSYNC')
@section('page_title', 'Dashboard')
@section('page_description', 'Attendance overview and recent activity.')

@section('content')
@if ($unconfiguredEmployeesCount > 0)
    <div class="alert alert-warning" style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:12px;">
        <x-lucide-triangle-alert class="w-5 h-5 text-amber-500 shrink-0" aria-hidden="true" />
        <div style="flex:1;">
            <strong>Configuration Warning:</strong>
            There are {{ $unconfiguredEmployeesCount }} active employee(s) with no assigned work schedule (and no default schedule template). Dynamic attendance rules and absence cutoffs cannot be evaluated for them until configured.
            <a href="{{ route('admin.employees.index') }}" style="color:#b45309; text-decoration:underline; font-weight:600; margin-left:8px;">Assign schedules &rarr;</a>
        </div>
    </div>
@endif
<section class="dashboard-welcome">
    <div>
        <span class="dashboard-eyebrow">BANSUD CENTRAL SCHOOL</span>
        <h2>Welcome back, {{ auth()->user()->name ?? 'Administrator' }}</h2>
        <p>Here is your attendance overview for {{ $today->format('l, F j, Y') }}.</p>
    </div>
    <a class="dashboard-action" href="{{ route('admin.attendance.index') }}">
        <span>View Attendance</span>
        <x-lucide-arrow-right class="dashboard-action-icon" aria-hidden="true" />
    </a>
</section>

<section class="stats-grid dashboard-stats" aria-label="Today's attendance summary">
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Employees</span>
            <span class="stat-icon blue" aria-hidden="true">
                <x-lucide-users class="w-5 h-5" />
            </span>
        </div>
        <div class="stat-number">{{ number_format($totalEmployees) }}</div>
        <div class="stat-note">Active employee accounts</div>
    </article>
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Present Today</span>
            <span class="stat-icon green" aria-hidden="true">
                <x-lucide-circle-check class="w-5 h-5" />
            </span>
        </div>
        <div class="stat-number">{{ number_format($presentToday) }}</div>
        <div class="stat-note">Recorded as on time</div>
    </article>
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Late Today</span>
            <span class="stat-icon orange" aria-hidden="true">
                <x-lucide-clock class="w-5 h-5" />
            </span>
        </div>
        <div class="stat-number">{{ number_format($lateToday) }}</div>
        <div class="stat-note">Recorded after start time</div>
    </article>
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Incomplete Today</span>
            <span class="stat-icon purple" aria-hidden="true" style="background:#f5f3ff; color:#7c3aed;">
                <x-lucide-alert-circle class="w-5 h-5" />
            </span>
        </div>
        <div class="stat-number">{{ number_format($incompleteToday) }}</div>
        <div class="stat-note">Missing required checkout</div>
    </article>
    <article class="stat-card stat-card-rate">
        <div class="stat-header">
            <span class="stat-label">Attendance Rate</span>
            <span class="stat-icon blue" aria-hidden="true">
                <x-lucide-chart-column class="w-5 h-5" />
            </span>
        </div>
        <div class="stat-number">
            @if ($totalEmployees > 0)
                {{ $attendanceRate }}<span class="stat-suffix">%</span>
            @else
                —
            @endif
        </div>
        <div class="stat-note">
            @if ($totalEmployees > 0)
                {{ number_format($checkedInToday) }} of {{ number_format($totalEmployees) }} active employees checked in
            @else
                No active employees
            @endif
        </div>
    </article>
</section>

<section class="dashboard-grid">
    <article class="panel dashboard-chart-panel">
        <div class="panel-heading">
            <div>
                <h3 class="panel-title">Attendance Overview</h3>
                <p class="panel-subtitle">On-time and late check-ins · last 7 days</p>
            </div>
            <div class="chart-legend" aria-label="Chart legend">
                <span><i class="legend-dot legend-present"></i>On time</span>
                <span><i class="legend-dot legend-late"></i>Late</span>
            </div>
        </div>

        @if ($totalEmployees === 0)
            <div class="dashboard-empty">
                <div class="empty-state-icon" aria-hidden="true">
                    <x-lucide-users class="w-8 h-8 text-slate-400" />
                </div>
                <strong>No active employees</strong>
                <span>Add employees to see attendance activity.</span>
                <a href="{{ route('admin.employees.create') }}">Add an employee</a>
            </div>
        @elseif ($weeklyAttendance->sum(fn ($day) => $day['present'] + $day['late']) === 0)
            <div class="dashboard-empty">
                <div class="empty-state-icon" aria-hidden="true">
                    <x-lucide-calendar class="w-8 h-8 text-slate-400" />
                </div>
                <strong>No check-ins recorded this week</strong>
                <span>Attendance activity will appear here when employees check in.</span>
            </div>
        @else
            <div class="attendance-chart" role="img" aria-label="Attendance counts for the last seven days">
                @foreach ($weeklyAttendance as $day)
                    @php
                        $dayTotal = $day['present'] + $day['late'];
                        $barHeight = $totalEmployees > 0 ? min(100, ($dayTotal / $totalEmployees) * 100) : 0;
                        $presentShare = $dayTotal > 0 ? ($day['present'] / $dayTotal) * 100 : 0;
                        $lateShare = $dayTotal > 0 ? ($day['late'] / $dayTotal) * 100 : 0;
                    @endphp
                    <div class="attendance-chart-column" title="{{ $day['date'] }}: {{ $day['present'] }} on time, {{ $day['late'] }} late">
                        <span class="attendance-chart-value">{{ $dayTotal }}</span>
                        <div class="attendance-chart-track">
                            <div class="attendance-chart-bar" style="height: {{ $barHeight }}%">
                                <span class="attendance-bar-late" style="height: {{ $lateShare }}%"></span>
                                <span class="attendance-bar-present" style="height: {{ $presentShare }}%"></span>
                            </div>
                        </div>
                        <span class="attendance-chart-day">{{ $day['day'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </article>

    <article class="panel dashboard-summary-panel">
        <div class="panel-heading">
            <div>
                <h3 class="panel-title">Today at a Glance</h3>
                <p class="panel-subtitle">Attendance records entered for today</p>
            </div>
        </div>
        @if ($attendanceTotal === 0)
            <div class="dashboard-empty dashboard-empty-compact">
                <strong>No attendance records yet</strong>
                <span>Today's totals will update as employees check in.</span>
            </div>
        @else
            <div class="status-row">
                <div class="status-top"><span class="status-name">On time</span><strong class="status-value">{{ $presentToday }} <small>{{ $presentPercentage }}%</small></strong></div>
                <div class="progress"><div class="progress-fill present-fill" style="width: {{ $presentPercentage }}%"></div></div>
            </div>
            <div class="status-row">
                <div class="status-top"><span class="status-name">Late</span><strong class="status-value">{{ $lateToday }} <small>{{ $latePercentage }}%</small></strong></div>
                <div class="progress"><div class="progress-fill late-fill" style="width: {{ $latePercentage }}%"></div></div>
            </div>
            <div class="status-row">
                <div class="status-top"><span class="status-name">Absent</span><strong class="status-value">{{ $absentToday }} <small>{{ $absentPercentage }}%</small></strong></div>
                <div class="progress"><div class="progress-fill absent-fill" style="width: {{ $absentPercentage }}%"></div></div>
            </div>
            <div class="status-row">
                <div class="status-top"><span class="status-name">Incomplete</span><strong class="status-value">{{ $incompleteToday }} <small>{{ $incompletePercentage }}%</small></strong></div>
                <div class="progress"><div class="progress-fill incomplete-fill" style="width: {{ $incompletePercentage }}%; background-color:#7c3aed;"></div></div>
            </div>
            <div class="summary-total-row"><span>On leave</span><strong>{{ $onLeaveToday }}</strong></div>
            <div class="summary-total-row"><span>Total attendance records</span><strong>{{ $attendanceTotal }}</strong></div>

            @if(isset($sessionStats))
                <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.05em;">Session Breakdown (Unique Sessions)</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                        <div style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <strong style="display: block; color: #1e293b; margin-bottom: 6px; font-weight: 600;">Morning (AM)</strong>
                            <div style="display:flex; justify-content:space-between; color: #16a34a; margin-bottom:2px;"><span>Present</span><strong>{{ $sessionStats['am']['present'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #ea580c; margin-bottom:2px;"><span>Late</span><strong>{{ $sessionStats['am']['late'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #7c3aed; margin-bottom:2px;"><span>Incomplete</span><strong>{{ $sessionStats['am']['incomplete'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #dc2626;"><span>Absent</span><strong>{{ $sessionStats['am']['absent'] ?? 0 }}</strong></div>
                        </div>
                        <div style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <strong style="display: block; color: #1e293b; margin-bottom: 6px; font-weight: 600;">Afternoon (PM)</strong>
                            <div style="display:flex; justify-content:space-between; color: #16a34a; margin-bottom:2px;"><span>Present</span><strong>{{ $sessionStats['pm']['present'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #ea580c; margin-bottom:2px;"><span>Late</span><strong>{{ $sessionStats['pm']['late'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #7c3aed; margin-bottom:2px;"><span>Incomplete</span><strong>{{ $sessionStats['pm']['incomplete'] ?? 0 }}</strong></div>
                            <div style="display:flex; justify-content:space-between; color: #dc2626;"><span>Absent</span><strong>{{ $sessionStats['pm']['absent'] ?? 0 }}</strong></div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </article>
</section>

<section class="panel report-panel dashboard-recent-panel">
    <div class="panel-heading">
        <div>
            <h3 class="panel-title">Recent Attendance Activity</h3>
            <p class="panel-subtitle">Latest saved employee attendance records</p>
        </div>
        <a href="{{ route('admin.attendance.index') }}" class="view-link">
            <span>View all attendance</span>
            <x-lucide-arrow-right class="w-4 h-4" aria-hidden="true" />
        </a>
    </div>

    <div class="table-responsive">
        <table class="report-table dashboard-recent-table">
            <thead>
                <tr><th>Employee</th><th>Attendance date</th><th>Time in</th><th>Time out</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($recentAttendances as $attendance)
                    <tr>
                        <td>
                            <span class="recent-employee-name">{{ $attendance->employee?->full_name ?? 'Employee unavailable' }}</span>
                            <small>{{ $attendance->employee?->employee_id ?? '—' }}</small>
                        </td>
                        <td>
                            @if ($attendance->attendance_date)
                                <time datetime="{{ $attendance->attendance_date->format('Y-m-d') }}">{{ $attendance->attendance_date->format('M j, Y') }}</time>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $attendance->am_time_in?->timezone(config('app.timezone'))->format('g:i A') ?? ($attendance->time_in?->timezone(config('app.timezone'))->format('g:i A') ?? '—') }}
                            @if ($attendance->am_time_out)
                                <small style="color:#64748B; display:block;">AM Out: {{ $attendance->am_time_out->timezone(config('app.timezone'))->format('g:i A') }}</small>
                            @endif
                        </td>
                        <td>
                            @if ($attendance->pm_time_in)
                                <small style="color:#64748B; display:block;">PM In: {{ $attendance->pm_time_in->timezone(config('app.timezone'))->format('g:i A') }}</small>
                            @endif
                            {{ $attendance->pm_time_out?->timezone(config('app.timezone'))->format('g:i A') ?? ($attendance->time_out?->timezone(config('app.timezone'))->format('g:i A') ?? '—') }}
                        </td>
                        <td>
                            @php
                                $statusClass = match ($attendance->status) {
                                    'present' => 'status-present',
                                    'late' => 'status-late',
                                    'absent' => 'status-absent',
                                    'incomplete' => 'status-incomplete',
                                    'on_leave' => 'status-leave',
                                    default => 'status-unknown',
                                };
                            @endphp
                            <span class="status-badge {{ $statusClass }}" @if($attendance->status === 'incomplete') style="background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe;" @endif>{{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="dashboard-empty dashboard-empty-table">
                                <x-lucide-clipboard-check class="w-8 h-8 text-slate-300" aria-hidden="true" style="margin: 0 auto 8px;" />
                                <strong>No attendance activity yet</strong>
                                <span>Saved attendance records will appear here.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
