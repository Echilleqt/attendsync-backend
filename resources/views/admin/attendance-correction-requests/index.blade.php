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
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Employee Requests</h3>
                <p>Open a request to review its reason, requested times, and current attendance.</p>
            </div>
            <form method="GET" action="{{ route('admin.attendance-correction-requests.index') }}" class="report-filter-form">
                <div class="report-filter-field filter-compact">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All requests</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="report-filter-actions">
                    <button type="submit" class="btn-primary">
                        <x-lucide-list-filter class="w-4 h-4" aria-hidden="true" />
                        <span>Filter</span>
                    </button>
                    @if ($status !== '')
                        <a href="{{ route('admin.attendance-correction-requests.index') }}" class="btn-secondary">
                            <span>Reset</span>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Attendance Date</th>
                        <th>Request Type</th>
                        <th>Requested Times</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="text-align: right;">Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $attendanceRequest)
                        <tr>
                            <td>
                                <strong>{{ $attendanceRequest->employee?->full_name ?? 'Employee unavailable' }}</strong><br>
                                <small class="table-meta-line">{{ $attendanceRequest->employee?->employee_id ?? '—' }}</small>
                            </td>
                            <td>{{ $attendanceRequest->attendance_date->format('M d, Y') }}</td>
                            <td>
                                <span class="badge badge-neutral">
                                    {{ ucfirst(str_replace('_', ' ', $attendanceRequest->request_type)) }}
                                </span>
                            </td>
                            <td>
                                <div><strong class="text-xs text-secondary">In:</strong> {{ $attendanceRequest->requested_time_in ? \Carbon\Carbon::parse($attendanceRequest->requested_time_in)->format('h:i A') : '—' }}</div>
                                <div><strong class="text-xs text-secondary">Out:</strong> {{ $attendanceRequest->requested_time_out ? \Carbon\Carbon::parse($attendanceRequest->requested_time_out)->format('h:i A') : '—' }}</div>
                            </td>
                            <td>
                                <span class="text-xs text-secondary">{{ $attendanceRequest->created_at->format('M d, Y h:i A') }}</span>
                            </td>
                            <td>
                                <span class="status-badge {{ $attendanceRequest->status === 'approved' ? 'status-present' : ($attendanceRequest->status === 'rejected' ? 'status-absent' : 'status-late') }}">
                                    {{ ucfirst($attendanceRequest->status) }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="employee-actions">
                                    <a class="table-action edit-action" href="{{ route('admin.attendance-correction-requests.show', $attendanceRequest) }}">
                                        <x-lucide-eye class="w-3.5 h-3.5" aria-hidden="true" />
                                        <span>{{ $attendanceRequest->status === 'pending' ? 'Evaluate' : 'Details' }}</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-file-pen-line class="w-8 h-8 text-slate-400" />
                                </div>
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
