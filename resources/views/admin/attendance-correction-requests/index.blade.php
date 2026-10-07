@extends('layouts.admin')

@section('title', 'Attendance Requests | ATTENDSYNC')
@section('page_title', 'Attendance Correction Requests')
@section('page_description', 'Review employee requests to correct attendance records.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">
                Review requests before applying any attendance changes.
                {{ $pendingCount }} pending {{ \Illuminate\Support\Str::plural('request', $pendingCount) }}.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Employee Requests</h3>
                <p>Open a request to review its reason, requested times, and current attendance.</p>
            </div>
            <form method="GET" action="{{ route('admin.attendance-correction-requests.index') }}" class="report-filter-form">
                <div class="report-filter-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All requests</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Filter</button>
                @if ($status !== '')
                    <a href="{{ route('admin.attendance-correction-requests.index') }}" class="btn-secondary">Reset</a>
                @endif
            </form>
        </div>

        <div class="employee-table-wrapper">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Attendance Date</th>
                        <th>Request Type</th>
                        <th>Requested Times</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $attendanceRequest)
                        <tr>
                            <td>
                                <strong>{{ $attendanceRequest->employee?->full_name ?? 'Employee unavailable' }}</strong><br>
                                <small>{{ $attendanceRequest->employee?->employee_id ?? '—' }}</small>
                            </td>
                            <td>{{ $attendanceRequest->attendance_date->format('M d, Y') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $attendanceRequest->request_type)) }}</td>
                            <td>
                                In: {{ $attendanceRequest->requested_time_in ? \Carbon\Carbon::parse($attendanceRequest->requested_time_in)->format('h:i A') : '—' }}<br>
                                Out: {{ $attendanceRequest->requested_time_out ? \Carbon\Carbon::parse($attendanceRequest->requested_time_out)->format('h:i A') : '—' }}
                            </td>
                            <td>{{ $attendanceRequest->created_at->format('M d, Y h:i A') }}</td>
                            <td>
                                <span class="status-badge {{ $attendanceRequest->status === 'approved' ? 'status-present' : ($attendanceRequest->status === 'rejected' ? 'status-absent' : 'status-late') }}">
                                    {{ ucfirst($attendanceRequest->status) }}
                                </span>
                            </td>
                            <td>
                                <a class="table-action edit-action" href="{{ route('admin.attendance-correction-requests.show', $attendanceRequest) }}">
                                    {{ $attendanceRequest->status === 'pending' ? 'Evaluate' : 'View details' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-employees">
                                <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 3.8h7l4 4V20H7a2 2 0 0 1-2-2V5.8a2 2 0 0 1 2-2Z"/><path d="M14 4v4h4M9 12h6M9 15.5h6"/></svg></div>
                                <h4>No correction requests found</h4>
                                <p>Employee requests will appear here when submitted.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="request-pagination">
            {{ $requests->links() }}
        </div>
    </div>
</section>
@endsection
