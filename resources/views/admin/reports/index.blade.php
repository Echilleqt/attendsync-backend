@extends('layouts.admin')

@section('title', 'Attendance Reports | ATTENDSYNC')
@section('page_title', 'Attendance Reports')
@section('page_description', 'Generate attendance reports for Bansud Central School.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">
                Generate attendance reports using actual attendance records.
            </p>
        </div>
    </div>

    {{-- FILTERS --}}
    <div class="employee-panel report-filter-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Report Filters</h3>
                <p>Select a date range and attendance status.</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('admin.reports.index') }}"
            class="report-filter-form"
        >
            <div class="report-filter-field filter-date">
                <label for="from_date">From Date</label>
                <input
                    type="date"
                    id="from_date"
                    name="from_date"
                    value="{{ $fromDate }}"
                    required
                >
            </div>

            <div class="report-filter-field filter-date">
                <label for="to_date">To Date</label>
                <input
                    type="date"
                    id="to_date"
                    name="to_date"
                    value="{{ $toDate }}"
                    required
                >
            </div>

            <div class="report-filter-field filter-compact">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="present" @selected($status === 'present')>Present</option>
                    <option value="late" @selected($status === 'late')>Late</option>
                    <option value="absent" @selected($status === 'absent')>Absent</option>
                    <option value="incomplete" @selected($status === 'incomplete')>Incomplete</option>
                    <option value="on_leave" @selected($status === 'on_leave')>On Leave</option>
                </select>
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">
                    <x-lucide-list-filter class="w-4 h-4" aria-hidden="true" />
                    <span>Apply Filter</span>
                </button>

                <a href="{{ route('admin.reports.index') }}" class="btn-secondary">
                    <span>Reset</span>
                </a>

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="window.print()"
                >
                    <x-lucide-printer class="w-4 h-4" aria-hidden="true" />
                    <span>Print</span>
                </button>
            </div>
        </form>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="report-summary-grid">
        <div class="report-summary-card">
            <span class="report-summary-label">Total Records</span>
            <strong>{{ $totalRecords }}</strong>
        </div>
        <div class="report-summary-card report-present">
            <span class="report-summary-label">Present</span>
            <strong>{{ $presentCount }}</strong>
        </div>
        <div class="report-summary-card report-late">
            <span class="report-summary-label">Late</span>
            <strong>{{ $lateCount }}</strong>
        </div>
        <div class="report-summary-card report-absent">
            <span class="report-summary-label">Absent</span>
            <strong>{{ $absentCount }}</strong>
        </div>
        <div class="report-summary-card report-incomplete" style="border-left: 4px solid #7c3aed;">
            <span class="report-summary-label">Incomplete</span>
            <strong>{{ $incompleteCount }}</strong>
        </div>
        <div class="report-summary-card report-leave">
            <span class="report-summary-label">On Leave</span>
            <strong>{{ $onLeaveCount }}</strong>
        </div>
    </div>

    {{-- REPORT TABLE --}}
    <div class="employee-panel report-table-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Attendance Report</h3>
                <p>{{ $fromDate }} to {{ $toDate }}</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attendances as $attendance)
                        <tr>
                            <td>
                                <strong>{{ $attendance->employee->full_name ?? 'Unknown Employee' }}</strong>
                                <small class="table-meta-line">{{ $attendance->employee->employee_id ?? '—' }}</small>
                            </td>
                            <td>
                                {{ $attendance->attendance_date ? \Carbon\Carbon::parse($attendance->attendance_date)->format('M d, Y') : '—' }}
                            </td>
                            <td>
                                @if ($attendance->am_time_in)
                                    <div><small style="color:#64748b;">AM:</small> {{ $attendance->am_time_in->format('h:i A') }}</div>
                                @elseif ($attendance->time_in)
                                    <div>{{ \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') }}</div>
                                @endif
                                @if ($attendance->pm_time_in)
                                    <div><small style="color:#64748b;">PM:</small> {{ $attendance->pm_time_in->format('h:i A') }}</div>
                                @endif
                                @if (!$attendance->am_time_in && !$attendance->time_in && !$attendance->pm_time_in)
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($attendance->am_time_out)
                                    <div><small style="color:#64748b;">AM:</small> {{ $attendance->am_time_out->format('h:i A') }}</div>
                                @endif
                                @if ($attendance->pm_time_out)
                                    <div><small style="color:#64748b;">PM:</small> {{ $attendance->pm_time_out->format('h:i A') }}</div>
                                @elseif ($attendance->time_out)
                                    <div>{{ \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') }}</div>
                                @endif
                                @if (!$attendance->am_time_out && !$attendance->time_out && !$attendance->pm_time_out)
                                    —
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusBadgeClass = match($attendance->status) {
                                        'present' => 'status-present',
                                        'late' => 'status-late',
                                        'absent' => 'status-absent',
                                        'incomplete' => 'status-incomplete',
                                        'on_leave' => 'status-leave',
                                        default => 'status-unknown',
                                    };
                                @endphp
                                <span class="status-badge {{ $statusBadgeClass }}" @if($attendance->status === 'incomplete') style="background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe;" @endif>
                                    {{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}
                                </span>
                                @if ($attendance->am_status || $attendance->pm_status)
                                    <small style="display:block; color:#64748b; margin-top:4px; font-size:11px;">
                                        AM: {{ ucfirst($attendance->am_status ?? '—') }} | PM: {{ ucfirst($attendance->pm_status ?? '—') }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-chart-column class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No attendance records found</h4>
                                <p>Try changing the date range or status filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="request-pagination">
            {{ $attendances->links() }}
        </div>
    </div>
</section>
@endsection