@extends('layouts.admin')

@section('title', 'Admin Dashboard | ATTENDSYNC')
@section('page_title', 'Dashboard')
@section('page_description', 'Attendance overview and recent activity.')

@section('content')
<section class="dashboard-welcome">
    <div>
        <span class="dashboard-eyebrow">BANSUD CENTRAL SCHOOL</span>
        <h2>Welcome back, {{ auth()->user()->name ?? 'Administrator' }}</h2>
        <p>Here is your attendance overview for {{ $today->format('l, F j, Y') }}.</p>
    </div>
    <a class="dashboard-action" href="{{ route('admin.attendance.index') }}">
        View attendance
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
    </a>
</section>

<section class="stats-grid dashboard-stats" aria-label="Today's attendance summary">
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Employees</span>
            <span class="stat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M17 14a4.8 4.8 0 0 1 3.5 4.6"/></svg></span>
        </div>
        <div class="stat-number">{{ number_format($totalEmployees) }}</div>
        <div class="stat-note">Active employee accounts</div>
    </article>
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Present Today</span>
            <span class="stat-icon green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7"/></svg></span>
        </div>
        <div class="stat-number">{{ number_format($presentToday) }}</div>
        <div class="stat-note">Recorded as on time</div>
    </article>
    <article class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Late Today</span>
            <span class="stat-icon orange" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/></svg></span>
        </div>
        <div class="stat-number">{{ number_format($lateToday) }}</div>
        <div class="stat-note">Recorded after start time</div>
    </article>
    <article class="stat-card stat-card-rate">
        <div class="stat-header">
            <span class="stat-label">Attendance Rate</span>
            <span class="stat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 17.5 9.1 6h2.6l5.2 11.5M6.1 13h8.6"/></svg></span>
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
                <h3 class="panel-title">Attendance overview</h3>
                <p class="panel-subtitle">On-time and late check-ins · last 7 days</p>
            </div>
            <div class="chart-legend" aria-label="Chart legend">
                <span><i class="legend-dot legend-present"></i>On time</span>
                <span><i class="legend-dot legend-late"></i>Late</span>
            </div>
        </div>

        @if ($totalEmployees === 0)
            <div class="dashboard-empty">
                <span class="empty-state-icon" aria-hidden="true">—</span>
                <strong>No active employees</strong>
                <span>Add employees to see attendance activity.</span>
                <a href="{{ route('admin.employees.create') }}">Add an employee</a>
            </div>
        @elseif ($weeklyAttendance->sum(fn ($day) => $day['present'] + $day['late']) === 0)
            <div class="dashboard-empty">
                <span class="empty-state-icon" aria-hidden="true">—</span>
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
                <h3 class="panel-title">Today at a glance</h3>
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
            <div class="summary-total-row"><span>On leave</span><strong>{{ $onLeaveToday }}</strong></div>
            <div class="summary-total-row"><span>Total attendance records</span><strong>{{ $attendanceTotal }}</strong></div>
        @endif
    </article>
</section>

<section class="panel report-panel dashboard-recent-panel">
    <div class="panel-heading">
        <div>
            <h3 class="panel-title">Recent attendance activity</h3>
            <p class="panel-subtitle">Latest saved employee attendance records</p>
        </div>
        <a href="{{ route('admin.attendance.index') }}" class="view-link">
            View all attendance
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
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
                                <small style="color:#6b7280; display:block;">AM Out: {{ $attendance->am_time_out->timezone(config('app.timezone'))->format('g:i A') }}</small>
                            @endif
                        </td>
                        <td>
                            @if ($attendance->pm_time_in)
                                <small style="color:#6b7280; display:block;">PM In: {{ $attendance->pm_time_in->timezone(config('app.timezone'))->format('g:i A') }}</small>
                            @endif
                            {{ $attendance->pm_time_out?->timezone(config('app.timezone'))->format('g:i A') ?? ($attendance->time_out?->timezone(config('app.timezone'))->format('g:i A') ?? '—') }}
                        </td>
                        <td>
                            @php
                                $statusClass = match ($attendance->status) {
                                    'present' => 'status-present',
                                    'late' => 'status-late',
                                    'absent' => 'status-absent',
                                    'on_leave' => 'status-leave',
                                    default => 'status-unknown',
                                };
                            @endphp
                            <span class="status-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="dashboard-empty dashboard-empty-table">
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
