@extends('layouts.admin')

@section('title', 'Attendance Management | ATTENDSYNC')

@section('page_title', 'Attendance Management')

@section('page_description', 'Monitor employee attendance records for Bansud Central School.')

@section('content')

<section class="employee-page">

    <div class="employee-heading">

        <div>
            <span class="school-label">
                BANSUD CENTRAL SCHOOL
            </span>

            <p class="employee-context">
                View and monitor employee attendance records.
            </p>
        </div>

    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="employee-panel report-filter-panel">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="report-filter-form">
            <div class="report-filter-field">
                <label for="from_date">From Date</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}" required>
            </div>
            <div class="report-filter-field">
                <label for="to_date">To Date</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}" required>
            </div>
            <div class="report-filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <option value="present" @selected($status === 'present')>Present</option>
                    <option value="late" @selected($status === 'late')>Late</option>
                    <option value="absent" @selected($status === 'absent')>Absent</option>
                    <option value="on_leave" @selected($status === 'on_leave')>On Leave</option>
                </select>
            </div>
            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">Apply Filter</button>
                <a href="{{ route('admin.attendance.index') }}" class="btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="report-summary-grid">
        <div class="report-summary-card"><span class="report-summary-label">Total Records</span><strong>{{ $totalRecords }}</strong></div>
        <div class="report-summary-card report-present"><span class="report-summary-label">Present</span><strong>{{ $presentCount }}</strong></div>
        <div class="report-summary-card report-late"><span class="report-summary-label">Late</span><strong>{{ $lateCount }}</strong></div>
        <div class="report-summary-card report-absent"><span class="report-summary-label">Absent</span><strong>{{ $absentCount }}</strong></div>
        <div class="report-summary-card report-leave"><span class="report-summary-label">On Leave</span><strong>{{ $onLeaveCount }}</strong></div>
    </div>

    <div class="employee-panel">

        <div class="employee-panel-header">

            <div>
                <h3>Attendance Records</h3>

                <p>
                    Recent employee attendance information
                </p>
            </div>

        </div>


        <div class="employee-table-wrapper">

            <table class="employee-table">

                <thead>

                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Morning (AM)</th>
                        <th>Afternoon (PM)</th>
                        <th>Status</th>
                        <th>Photo Proof</th>
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

                                {{ $attendance->attendance_date
                                    ? \Carbon\Carbon::parse($attendance->attendance_date)->format('M d, Y')
                                    : '—' }}

                            </td>


                            <td>
                                <div><strong>In:</strong> {{ $attendance->am_time_in ? \Carbon\Carbon::parse($attendance->am_time_in)->format('h:i A') : ($attendance->time_in ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') : '—') }}</div>
                                <div><strong>Out:</strong> {{ $attendance->am_time_out ? \Carbon\Carbon::parse($attendance->am_time_out)->format('h:i A') : '—' }}</div>
                                @if ($attendance->am_time_in_verification_method || $attendance->time_in_verification_method)
                                    <small style="color: #64748b; font-size: 11px;">
                                        Method: {{ ($attendance->am_time_in_verification_method ?? $attendance->time_in_verification_method) === 'face' ? 'Face' : 'Fingerprint' }}
                                    </small>
                                @endif
                            </td>


                            <td>
                                <div><strong>In:</strong> {{ $attendance->pm_time_in ? \Carbon\Carbon::parse($attendance->pm_time_in)->format('h:i A') : '—' }}</div>
                                <div><strong>Out:</strong> {{ $attendance->pm_time_out ? \Carbon\Carbon::parse($attendance->pm_time_out)->format('h:i A') : ($attendance->time_out ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') : '—') }}</div>
                                @if ($attendance->pm_time_out_verification_method || $attendance->time_out_verification_method)
                                    <small style="color: #64748b; font-size: 11px;">
                                        Method: {{ ($attendance->pm_time_out_verification_method ?? $attendance->time_out_verification_method) === 'face' ? 'Face' : 'Fingerprint' }}
                                    </small>
                                @endif
                            </td>


                            <td>

                                <span class="status-badge
                                    {{ $attendance->status === 'present'
                                        ? 'status-present'
                                        : ($attendance->status === 'late'
                                            ? 'status-late'
                                            : ($attendance->status === 'absent'
                                                ? 'status-absent'
                                                : 'status-leave')) }}">

                                    {{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}

                                </span>

                            </td>

                            <td>
                                @if ($attendance->photo_path)
                                    <a
                                        href="{{ route('admin.attendance.photo', $attendance) }}"
                                        target="_blank"
                                        rel="noopener"
                                    >View photo</a>
                                @else
                                    —
                                @endif
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6" class="empty-employees">

                                <div class="empty-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/></svg>
                                </div>

                                <h4>
                                    No attendance records yet
                                </h4>

                                <p>
                                    Attendance records will appear here
                                    once employees record their attendance.
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