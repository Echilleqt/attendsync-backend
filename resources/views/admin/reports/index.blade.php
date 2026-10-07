
@extends('layouts.admin')

@section('title', 'Attendance Reports | ATTENDSYNC')

@section('page_title', 'Attendance Reports')

@section('page_description', 'Generate attendance reports for Bansud Central School.')

@section('content')

<section class="employee-page">

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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

                <p>
                    Select a date range and attendance status.
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('admin.reports.index') }}"
            class="report-filter-form"
        >

            <div class="report-filter-field">
                <label for="from_date">From Date</label>

                <input
                    type="date"
                    id="from_date"
                    name="from_date"
                    value="{{ $fromDate }}"
                    required
                >
            </div>

            <div class="report-filter-field">
                <label for="to_date">To Date</label>

                <input
                    type="date"
                    id="to_date"
                    name="to_date"
                    value="{{ $toDate }}"
                    required
                >
            </div>

            <div class="report-filter-field">
                <label for="status">Status</label>

                <select id="status" name="status">
                    <option value="">All Statuses</option>

                    <option
                        value="present"
                        @selected($status === 'present')
                    >
                        Present
                    </option>

                    <option
                        value="late"
                        @selected($status === 'late')
                    >
                        Late
                    </option>

                    <option
                        value="absent"
                        @selected($status === 'absent')
                    >
                        Absent
                    </option>

                    <option
                        value="on_leave"
                        @selected($status === 'on_leave')
                    >
                        On Leave
                    </option>
                </select>
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">
                    Apply Filter
                </button>

                <a
                    href="{{ route('admin.reports.index') }}"
                    class="btn-secondary"
                >
                    Reset
                </a>

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="window.print()"
                >
                    <svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 8V4h10v4M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v6H7z"/></svg>
                    Print
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

                <p>
                    {{ $fromDate }} to {{ $toDate }}
                </p>
            </div>
        </div>

        <div class="employee-table-wrapper">

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
                                <strong>
                                    {{ $attendance->employee->full_name ?? 'Unknown Employee' }}
                                </strong>

                                <br>

                                <small>
                                    {{ $attendance->employee->employee_id ?? '—' }}
                                </small>
                            </td>

                            <td>
                                {{
                                    $attendance->attendance_date
                                        ? \Carbon\Carbon::parse($attendance->attendance_date)->format('M d, Y')
                                        : '—'
                                }}
                            </td>

                            <td>
                                {{
                                    $attendance->time_in
                                        ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A')
                                        : '—'
                                }}
                            </td>

                            <td>
                                {{
                                    $attendance->time_out
                                        ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A')
                                        : '—'
                                }}
                            </td>

                            <td>
                                <span class="status-badge
                                    {{
                                        $attendance->status === 'present'
                                            ? 'status-present'
                                            : (
                                                $attendance->status === 'late'
                                                    ? 'status-late'
                                                    : (
                                                        $attendance->status === 'absent'
                                                            ? 'status-absent'
                                                            : 'status-leave'
                                                    )
                                            )
                                    }}"
                                >
                                    {{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}
                                </span>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="5" class="empty-employees">

                                <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 3.8h7l4 4V20H7a2 2 0 0 1-2-2V5.8a2 2 0 0 1 2-2Z"/><path d="M14 4v4h4M9 12h6M9 15.5h6"/></svg></div>

                                <h4>No attendance records found</h4>

                                <p>
                                    Try changing the date range or status filter.
                                </p>

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