@extends('layouts.admin')

@section('title', 'Evaluate Attendance Request | ATTENDSYNC')
@section('page_title', 'Evaluate Attendance Request')
@section('page_description', 'Review the employee request and decide whether to apply the correction.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Request #{{ $attendanceRequest->id }}</p>
        </div>
        <a href="{{ route('admin.attendance-correction-requests.index') }}" class="btn-secondary">
            <x-lucide-arrow-left class="w-4 h-4" aria-hidden="true" />
            <span>Back to Requests</span>
        </a>
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
                <strong>Unable to complete the review:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Employee and Request</h3>
                <p>{{ $attendanceRequest->employee?->full_name ?? 'Employee unavailable' }} · {{ $attendanceRequest->employee?->employee_id ?? '—' }}</p>
            </div>
            <span class="status-badge {{ $attendanceRequest->status === 'approved' ? 'status-present' : ($attendanceRequest->status === 'rejected' ? 'status-absent' : 'status-late') }}">
                {{ ucfirst($attendanceRequest->status) }}
            </span>
        </div>

        <div class="request-details-grid">
            <div><strong>Attendance date</strong><span>{{ $attendanceRequest->attendance_date ? \Carbon\Carbon::parse($attendanceRequest->attendance_date)->format('F d, Y') : '—' }}</span></div>
            <div><strong>Request type</strong><span>{{ ucfirst(str_replace('_', ' ', $attendanceRequest->request_type)) }}</span></div>
            <div><strong>Requested Time In</strong><span>{{ $attendanceRequest->requested_time_in ? \Carbon\Carbon::parse($attendanceRequest->requested_time_in)->format('h:i A') : 'Not provided' }}</span></div>
            <div><strong>Requested Time Out</strong><span>{{ $attendanceRequest->requested_time_out ? \Carbon\Carbon::parse($attendanceRequest->requested_time_out)->format('h:i A') : 'Not provided' }}</span></div>
            <div><strong>Submitted</strong><span>{{ $attendanceRequest->created_at ? \Carbon\Carbon::parse($attendanceRequest->created_at)->format('F d, Y h:i A') : '—' }}</span></div>
            <div><strong>Current attendance status</strong><span>{{ isset($attendance) && $attendance?->status ? ucfirst(str_replace('_', ' ', $attendance->status)) : 'No attendance record' }}</span></div>
            <div><strong>Current Time In</strong><span>{{ isset($attendance) && $attendance?->time_in ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') : '—' }}</span></div>
            <div><strong>Current Time Out</strong><span>{{ isset($attendance) && $attendance?->time_out ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') : '—' }}</span></div>
        </div>

        <div class="request-reason">
            <strong>Employee reason:</strong>
            <p>{{ $attendanceRequest->reason }}</p>
        </div>

        @if ($attendanceRequest->status === 'pending')
            <hr class="panel-divider">
            <form
                method="POST"
                action="{{ route('admin.attendance-correction-requests.approve', ['correctionRequest' => $attendanceRequest->id]) }}"
                data-confirm-message="Approve and apply this attendance correction to the official attendance record?"
                data-confirm-title="Approve Correction"
                data-confirm-type="success"
                data-confirm-btn="Approve"
                class="panel-form"
            >
                @csrf
                <div class="form-group">
                    <label for="approve_notes">Review notes (optional)</label>
                    <textarea id="approve_notes" name="admin_review_notes" rows="3" maxlength="2000" placeholder="Optional notes on approval">{{ old('admin_review_notes') }}</textarea>
                </div>
                <div class="form-actions-row">
                    <button type="submit" class="btn-primary">
                        <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                        <span>Approve and Apply Correction</span>
                    </button>
                </div>
            </form>

            <form
                method="POST"
                action="{{ route('admin.attendance-correction-requests.reject', ['correctionRequest' => $attendanceRequest->id]) }}"
                data-confirm-message="Are you sure you want to reject this employee attendance correction request?"
                data-confirm-title="Reject Correction"
                data-confirm-type="danger"
                data-confirm-btn="Reject"
                class="panel-form mt-4"
            >
                @csrf
                <div class="form-group">
                    <label for="reject_notes">Review notes (optional)</label>
                    <textarea id="reject_notes" name="admin_review_notes" rows="3" maxlength="2000" placeholder="Reason for rejection">{{ old('admin_review_notes') }}</textarea>
                </div>
                <div class="form-actions-row">
                    <button type="submit" class="table-action delete-action">
                        <x-lucide-circle-x class="w-4 h-4" aria-hidden="true" />
                        <span>Reject Request</span>
                    </button>
                </div>
            </form>
        @else
            <div class="request-review-summary">
                <h4>Review record</h4>
                <p><strong>Reviewed by:</strong> {{ $attendanceRequest->reviewer?->name ?? 'Administrator unavailable' }}</p>
                <p><strong>Reviewed at:</strong> {{ $attendanceRequest->reviewed_at?->format('F d, Y h:i A') ?? '—' }}</p>
                <p><strong>Admin notes:</strong> {{ $attendanceRequest->admin_review_notes ?: 'No notes provided.' }}</p>
                @if ($attendanceRequest->original_values)
                    <p><strong>Original values:</strong></p>
                    <pre class="bg-slate-100 p-3 rounded text-xs">{{ json_encode($attendanceRequest->original_values, JSON_PRETTY_PRINT) }}</pre>
                    <p><strong>Approved values:</strong></p>
                    <pre class="bg-slate-100 p-3 rounded text-xs">{{ json_encode($attendanceRequest->approved_values, JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        @endif
    </div>
</section>
@endsection
