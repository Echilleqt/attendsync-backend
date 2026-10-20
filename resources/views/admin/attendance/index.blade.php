@extends('layouts.admin')

@section('title', 'Attendance Management | ATTENDSYNC')
@section('page_title', 'Attendance Management')
@section('page_description', 'Monitor 4-entry employee attendance records, shift schedules, and photo proof for Bansud Central School.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">View and monitor 4-entry employee attendance, photo verification evidence, and time compliance.</p>
        </div>
    </div>

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

    {{-- Filter Panel --}}
    <div class="employee-panel report-filter-panel">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="report-filter-form">
            <div class="report-filter-field filter-date">
                <label for="from_date">From Date</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}" required>
            </div>

            <div class="report-filter-field filter-date">
                <label for="to_date">To Date</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}" required>
            </div>

            <div class="report-filter-field filter-grow">
                <label for="employee_id">Employee</label>
                <select id="employee_id" name="employee_id">
                    <option value="">All Employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected((string)$employeeId === (string)$emp->id)>
                            {{ $emp->full_name }} ({{ $emp->employee_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string)$departmentId === (string)$dept->id)>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field">
                <label for="position_id">Position</label>
                <select id="position_id" name="position_id">
                    <option value="">All Positions</option>
                    @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" @selected((string)$positionId === (string)$pos->id)>
                            {{ $pos->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field">
                <label for="schedule_id">Schedule</label>
                <select id="schedule_id" name="schedule_id">
                    <option value="">All Schedules</option>
                    @foreach($schedules as $sched)
                        <option value="{{ $sched->id }}" @selected((string)$scheduleId === (string)$sched->id)>
                            {{ $sched->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field filter-compact">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="present" @selected($status === 'present')>Present</option>
                    <option value="late" @selected($status === 'late')>Late</option>
                    <option value="incomplete" @selected($status === 'incomplete')>Incomplete</option>
                    <option value="absent" @selected($status === 'absent')>Absent</option>
                    <option value="on_leave" @selected($status === 'on_leave')>On Leave</option>
                </select>
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">
                    <x-lucide-list-filter class="w-4 h-4" aria-hidden="true" />
                    <span>Apply Filter</span>
                </button>
                <a href="{{ route('admin.attendance.index') }}" class="btn-secondary">
                    <span>Reset</span>
                </a>
            </div>
        </form>
    </div>

    @if(!empty($unconfiguredEmployeesCount) && $unconfiguredEmployeesCount > 0)
        <div class="alert alert-warning" style="margin-bottom: 1.5rem; background-color: #FFFBEB; border-color: #FCD34D; color: #92400E; display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border-radius: 0.5rem;">
            <x-lucide-triangle-alert class="alert-icon text-amber-600" aria-hidden="true" />
            <div>
                <strong>Configuration Alert:</strong> {{ $unconfiguredEmployeesCount }} active employee(s) lack an assigned work schedule and no default schedule is active.
                <a href="{{ route('admin.schedules.index') }}" style="color: #1E3A8A; font-weight: 700; text-decoration: underline; margin-left: 0.5rem;">Assign Schedules</a>
            </div>
        </div>
    @endif

    {{-- Summary Grid --}}
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
        <div class="report-summary-card" style="border-top: 3px solid #6366F1;">
            <span class="report-summary-label">Incomplete</span>
            <strong style="color: #4F46E5;">{{ $incompleteCount ?? 0 }}</strong>
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

    {{-- Attendance Table --}}
    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Attendance Records ({{ $attendances->total() }})</h3>
                <p>4-entry shift verification and time tracking</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date &amp; Schedule</th>
                        <th>Morning (AM)</th>
                        <th>Afternoon (PM)</th>
                        <th>Status</th>
                        <th>Photo Proofs</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($attendances as $attendance)
                        @php
                            $amInPhoto = $attendance->am_time_in_photo_path ?? $attendance->photo_path;
                            $amOutPhoto = $attendance->am_time_out_photo_path;
                            $pmInPhoto = $attendance->pm_time_in_photo_path;
                            $pmOutPhoto = $attendance->pm_time_out_photo_path ?? $attendance->time_out_photo_path;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $attendance->employee->full_name ?? 'Unknown Employee' }}</strong>
                                <small class="table-meta-line">
                                    {{ $attendance->employee->employee_id ?? '—' }} • {{ $attendance->employee->positionModel->name ?? $attendance->employee->position ?? '' }}
                                </small>
                            </td>

                            <td>
                                <div><strong>{{ $attendance->attendance_date ? \Carbon\Carbon::parse($attendance->attendance_date)->format('M d, Y') : '—' }}</strong></div>
                                <span class="badge badge-info mt-1">
                                    {{ $attendance->schedule->name ?? ($attendance->employee->schedule->name ?? 'Standard') }}
                                </span>
                            </td>

                            <td>
                                <div>
                                    <strong class="text-xs text-secondary">In:</strong>
                                    <span>{{ $attendance->am_time_in ? \Carbon\Carbon::parse($attendance->am_time_in)->format('h:i A') : ($attendance->time_in ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') : '—') }}</span>
                                </div>
                                <div>
                                    <strong class="text-xs text-secondary">Out:</strong>
                                    <span>{{ $attendance->am_time_out ? \Carbon\Carbon::parse($attendance->am_time_out)->format('h:i A') : '—' }}</span>
                                </div>
                                @if($attendance->am_status)
                                    <span class="badge {{ $attendance->am_status === 'present' ? 'badge-success' : ($attendance->am_status === 'late' ? 'badge-warning' : ($attendance->am_status === 'incomplete' ? 'badge-secondary' : 'badge-danger')) }} mt-1 text-xs">
                                        AM: {{ ucfirst($attendance->am_status) }}
                                    </span>
                                @endif
                                @if ($attendance->am_time_in_verification_method || $attendance->time_in_verification_method)
                                    <small class="table-meta-line">
                                        Method: {{ ($attendance->am_time_in_verification_method ?? $attendance->time_in_verification_method) === 'face' ? 'Face' : 'Fingerprint' }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                <div>
                                    <strong class="text-xs text-secondary">In:</strong>
                                    <span>{{ $attendance->pm_time_in ? \Carbon\Carbon::parse($attendance->pm_time_in)->format('h:i A') : '—' }}</span>
                                </div>
                                <div>
                                    <strong class="text-xs text-secondary">Out:</strong>
                                    <span>{{ $attendance->pm_time_out ? \Carbon\Carbon::parse($attendance->pm_time_out)->format('h:i A') : ($attendance->time_out ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') : '—') }}</span>
                                </div>
                                @if($attendance->pm_status)
                                    <span class="badge {{ $attendance->pm_status === 'present' ? 'badge-success' : ($attendance->pm_status === 'late' ? 'badge-warning' : ($attendance->pm_status === 'incomplete' ? 'badge-secondary' : 'badge-danger')) }} mt-1 text-xs">
                                        PM: {{ ucfirst($attendance->pm_status) }}
                                    </span>
                                @endif
                                @if ($attendance->pm_time_out_verification_method || $attendance->time_out_verification_method)
                                    <small class="table-meta-line">
                                        Method: {{ ($attendance->pm_time_out_verification_method ?? $attendance->time_out_verification_method) === 'face' ? 'Face' : 'Fingerprint' }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                <span class="status-badge {{ $attendance->status === 'present' ? 'status-present' : ($attendance->status === 'late' ? 'status-late' : ($attendance->status === 'incomplete' ? 'status-incomplete' : ($attendance->status === 'absent' ? 'status-absent' : 'status-leave'))) }}">
                                    {{ ucfirst(str_replace('_', ' ', $attendance->status ?? 'Unknown')) }}
                                </span>
                            </td>

                            <td>
                                <div class="badge-group">
                                    @if($amInPhoto)
                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openPhotoProofModal('{{ route('admin.attendance.photo', ['attendance' => $attendance->id, 'entry' => 'am-in']) }}', '{{ $attendance->employee->full_name ?? '' }}', 'Morning Time In', '{{ $attendance->am_time_in ? \Carbon\Carbon::parse($attendance->am_time_in)->format('M d, Y h:i A') : '' }}')"
                                            title="View morning time-in photo"
                                        >
                                            <x-lucide-eye class="w-3 h-3" aria-hidden="true" />
                                            <span>AM In</span>
                                        </button>
                                    @endif

                                    @if($amOutPhoto)
                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openPhotoProofModal('{{ route('admin.attendance.photo', ['attendance' => $attendance->id, 'entry' => 'am-out']) }}', '{{ $attendance->employee->full_name ?? '' }}', 'Morning Time Out', '{{ $attendance->am_time_out ? \Carbon\Carbon::parse($attendance->am_time_out)->format('M d, Y h:i A') : '' }}')"
                                            title="View morning time-out photo"
                                        >
                                            <x-lucide-eye class="w-3 h-3" aria-hidden="true" />
                                            <span>AM Out</span>
                                        </button>
                                    @endif

                                    @if($pmInPhoto)
                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openPhotoProofModal('{{ route('admin.attendance.photo', ['attendance' => $attendance->id, 'entry' => 'pm-in']) }}', '{{ $attendance->employee->full_name ?? '' }}', 'Afternoon Time In', '{{ $attendance->pm_time_in ? \Carbon\Carbon::parse($attendance->pm_time_in)->format('M d, Y h:i A') : '' }}')"
                                            title="View afternoon time-in photo"
                                        >
                                            <x-lucide-eye class="w-3 h-3" aria-hidden="true" />
                                            <span>PM In</span>
                                        </button>
                                    @endif

                                    @if($pmOutPhoto)
                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openPhotoProofModal('{{ route('admin.attendance.photo', ['attendance' => $attendance->id, 'entry' => 'pm-out']) }}', '{{ $attendance->employee->full_name ?? '' }}', 'Afternoon Time Out', '{{ $attendance->pm_time_out ? \Carbon\Carbon::parse($attendance->pm_time_out)->format('M d, Y h:i A') : '' }}')"
                                            title="View afternoon time-out photo"
                                        >
                                            <x-lucide-eye class="w-3 h-3" aria-hidden="true" />
                                            <span>PM Out</span>
                                        </button>
                                    @endif

                                    @if(!$amInPhoto && !$amOutPhoto && !$pmInPhoto && !$pmOutPhoto)
                                        <span class="text-xs text-slate-400">No photo</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-clipboard-check class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No attendance records found</h4>
                                <p>No records match the selected date range or employee filter criteria.</p>
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

<!-- Admin Photo Proof Viewer Modal -->
<div id="photoProofModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card max-w-lg">
        <div class="custom-modal-header bg-slate-900 text-white">
            <div>
                <h4 id="photoModalTitle" class="text-white font-bold text-base m-0">Attendance Photo Proof</h4>
                <span id="photoModalSubtitle" class="text-slate-400 text-xs">Bansud Central School Biometric Evidence</span>
            </div>
            <button type="button" onclick="closePhotoProofModal()" class="text-white hover:text-slate-200 cursor-pointer p-1" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5 text-white" aria-hidden="true" />
            </button>
        </div>

        <div class="p-5 text-center bg-slate-950">
            <div id="photoLoading" class="text-slate-400 py-10 text-sm">
                <x-lucide-loader-circle class="w-6 h-6 animate-spin mx-auto mb-2 text-blue-500" />
                <span>Loading photo proof...</span>
            </div>
            <img
                id="photoModalImage"
                src=""
                alt="Attendance Photo Proof"
                class="max-w-full max-h-[380px] rounded-lg mx-auto object-contain hidden"
                onload="onPhotoLoaded()"
                onerror="onPhotoError()"
            >
            <div id="photoError" class="hidden text-rose-400 py-8 text-sm">
                <x-lucide-triangle-alert class="w-6 h-6 mx-auto mb-2 text-rose-500" />
                <span>Unable to load attendance photo proof from storage.</span>
            </div>
        </div>

        <div class="p-3 px-5 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
            <span id="photoModalTimestamp" class="text-xs text-slate-500 font-semibold"></span>
            <button type="button" onclick="closePhotoProofModal()" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
function openPhotoProofModal(photoUrl, employeeName, eventName, timestamp) {
    const modal = document.getElementById('photoProofModal');
    const img = document.getElementById('photoModalImage');
    const loading = document.getElementById('photoLoading');
    const error = document.getElementById('photoError');
    const title = document.getElementById('photoModalTitle');
    const subtitle = document.getElementById('photoModalSubtitle');
    const timeEl = document.getElementById('photoModalTimestamp');

    title.textContent = eventName + ' Proof';
    subtitle.textContent = employeeName;
    timeEl.textContent = timestamp ? 'Timestamp: ' + timestamp : '';

    img.style.display = 'none';
    error.style.display = 'none';
    loading.style.display = 'block';

    img.src = photoUrl;
    modal.style.display = 'flex';
}

function onPhotoLoaded() {
    document.getElementById('photoLoading').style.display = 'none';
    document.getElementById('photoModalImage').style.display = 'block';
}

function onPhotoError() {
    document.getElementById('photoLoading').style.display = 'none';
    document.getElementById('photoError').style.display = 'block';
}

function closePhotoProofModal() {
    document.getElementById('photoProofModal').style.display = 'none';
    document.getElementById('photoModalImage').src = '';
}
</script>
@endsection