@extends('layouts.admin')

@section('title', 'Schedules — ATTENDSYNC')
@section('page_title', 'Work Schedule Management')
@section('page_description', 'Configure 4-entry daily shifts, template schedules, and individual employee assignments.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Manage reusable shift templates and individual employee schedule assignments.</p>
        </div>
        <div class="page-action-group">
            <button type="button" class="btn-primary" onclick="openAssignModal()">
                <x-lucide-users class="w-4 h-4" aria-hidden="true" />
                <span>Assign to Employee</span>
            </button>
            <button type="button" class="btn-secondary" onclick="openCustomModal()">
                <x-lucide-calendar-clock class="w-4 h-4" aria-hidden="true" />
                <span>Custom Schedule</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            <x-lucide-circle-x class="alert-icon" aria-hidden="true" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <x-lucide-triangle-alert class="alert-icon" aria-hidden="true" />
            <div>
                <strong>Please resolve the following errors:</strong>
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="admin-split-layout">
        <!-- Schedules List -->
        <div class="employee-panel panel-fill">
            <div class="employee-panel-header">
                <div>
                    <h3>Work Schedule Templates ({{ $schedules->total() }})</h3>
                    <p>Standard school faculty and administrative shift definitions</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Schedule Name</th>
                            <th>Morning (AM)</th>
                            <th>Afternoon (PM)</th>
                            <th>Assigned</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $sched)
                            <tr>
                                <td>
                                    <strong>{{ $sched->name }}</strong>
                                    @if($sched->is_default)
                                        <span class="badge badge-success ml-1">DEFAULT</span>
                                    @endif
                                    @if($sched->description)
                                        <div class="table-meta-line">{{ $sched->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div><strong>{{ \Carbon\Carbon::parse($sched->am_time_in)->format('h:i A') }}</strong> – {{ \Carbon\Carbon::parse($sched->am_time_out)->format('h:i A') }}</div>
                                    <small class="text-amber-700 block text-xs">Late after {{ \Carbon\Carbon::parse($sched->late_threshold_am)->format('h:i A') }}</small>
                                </td>
                                <td>
                                    <div><strong>{{ \Carbon\Carbon::parse($sched->pm_time_in)->format('h:i A') }}</strong> – {{ \Carbon\Carbon::parse($sched->pm_time_out)->format('h:i A') }}</div>
                                    <small class="text-amber-700 block text-xs">Late after {{ \Carbon\Carbon::parse($sched->late_threshold_pm)->format('h:i A') }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('admin.schedules.show', $sched) }}" class="no-underline">
                                        <span class="badge badge-info hover-trigger">
                                            <x-lucide-users class="w-3 h-3" aria-hidden="true" />
                                            <span>{{ $sched->employees_count }} employees</span>
                                            <x-lucide-arrow-right class="w-3 h-3" aria-hidden="true" />
                                        </span>
                                    </a>
                                </td>
                                <td style="text-align: right;">
                                    <div class="employee-actions">
                                        <a href="{{ route('admin.schedules.show', $sched) }}" class="table-action edit-action" title="View details">
                                            <x-lucide-eye class="w-3.5 h-3.5" aria-hidden="true" />
                                            <span>Details</span>
                                        </a>

                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openEditScheduleModal({{ json_encode($sched) }})"
                                            title="Edit template"
                                        >
                                            <x-lucide-pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                            <span>Edit</span>
                                        </button>

                                        @if(!$sched->is_default)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.schedules.destroy', $sched) }}"
                                                style="display: inline;"
                                                data-confirm-message="Are you sure you want to archive schedule '{{ $sched->name }}'? Deletion is blocked if employees are currently assigned."
                                                data-confirm-title="Archive Schedule"
                                                data-confirm-type="danger"
                                                data-confirm-btn="Archive"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="table-action delete-action"
                                                    {{ $sched->employees_count > 0 ? 'disabled title="Cannot delete schedule with assigned employees"' : '' }}
                                                    style="{{ $sched->employees_count > 0 ? 'opacity: 0.5; cursor: not-allowed;' : '' }}"
                                                >
                                                    <x-lucide-trash-2 class="w-3.5 h-3.5" aria-hidden="true" />
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-employees">
                                    <div class="empty-icon" aria-hidden="true">
                                        <x-lucide-calendar-clock class="w-8 h-8 text-slate-400" />
                                    </div>
                                    <h4>No work schedules configured</h4>
                                    <p>Use the form on the right to register schedules (e.g. Grade School, Kinder, Admin shifts).</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="request-pagination">
                {{ $schedules->links() }}
            </div>
        </div>

        <!-- Add Schedule Template Form -->
        <div class="employee-panel panel-sidebar">
            <div class="employee-panel-header panel-sidebar-header">
                <div>
                    <h3>Add 4-Entry Schedule</h3>
                    <p>Create reusable shift template</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.schedules.store') }}" class="panel-form">
                @csrf
                <div class="form-group">
                    <label for="sched_name">Schedule Name *</label>
                    <input type="text" id="sched_name" name="name" value="{{ old('name') }}" required placeholder="e.g. Special Shift A">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="sched_am_in">AM Time In *</label>
                        <input type="time" id="sched_am_in" name="am_time_in" value="{{ old('am_time_in', '07:30') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="sched_am_out">AM Time Out *</label>
                        <input type="time" id="sched_am_out" name="am_time_out" value="{{ old('am_time_out', '12:00') }}" required>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="sched_pm_in">PM Time In *</label>
                        <input type="time" id="sched_pm_in" name="pm_time_in" value="{{ old('pm_time_in', '13:00') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="sched_pm_out">PM Time Out *</label>
                        <input type="time" id="sched_pm_out" name="pm_time_out" value="{{ old('pm_time_out', '16:30') }}" required>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="sched_late_am">AM Late Cutoff</label>
                        <input type="time" id="sched_late_am" name="late_threshold_am" value="{{ old('late_threshold_am', '07:30') }}">
                    </div>
                    <div class="form-group">
                        <label for="sched_late_pm">PM Late Cutoff</label>
                        <input type="time" id="sched_late_pm" name="late_threshold_pm" value="{{ old('late_threshold_pm', '13:00') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="sched_desc">Description</label>
                    <textarea id="sched_desc" name="description" rows="2" placeholder="Optional shift notes">{{ old('description') }}</textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                        <span>Set as Default School Schedule</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full">
                    <x-lucide-plus class="w-4 h-4" aria-hidden="true" />
                    <span>Create Schedule Template</span>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h3>Edit Schedule Template</h3>
            <button type="button" onclick="closeEditScheduleModal()" class="custom-modal-close" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>

        <form id="editScheduleForm" method="POST" action="" class="panel-form">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="edit_sched_name">Schedule Name *</label>
                <input type="text" id="edit_sched_name" name="name" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="edit_sched_am_in">AM Time In *</label>
                    <input type="time" id="edit_sched_am_in" name="am_time_in" required>
                </div>
                <div class="form-group">
                    <label for="edit_sched_am_out">AM Time Out *</label>
                    <input type="time" id="edit_sched_am_out" name="am_time_out" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="edit_sched_pm_in">PM Time In *</label>
                    <input type="time" id="edit_sched_pm_in" name="pm_time_in" required>
                </div>
                <div class="form-group">
                    <label for="edit_sched_pm_out">PM Time Out *</label>
                    <input type="time" id="edit_sched_pm_out" name="pm_time_out" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="edit_sched_late_am">AM Late Cutoff</label>
                    <input type="time" id="edit_sched_late_am" name="late_threshold_am">
                </div>
                <div class="form-group">
                    <label for="edit_sched_late_pm">PM Late Cutoff</label>
                    <input type="time" id="edit_sched_late_pm" name="late_threshold_pm">
                </div>
            </div>

            <div class="form-group">
                <label for="edit_sched_desc">Description</label>
                <textarea id="edit_sched_desc" name="description" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="edit_sched_default" name="is_default" value="1">
                    <span>Set as Default School Schedule</span>
                </label>
            </div>

            <div class="form-actions-row">
                <button type="button" onclick="closeEditScheduleModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 1: Assign Schedule to Specific Employee -->
<div id="assignScheduleModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <div>
                <h3>Assign Schedule to Employee</h3>
                <span class="custom-modal-subtitle">Changing one employee never affects others</span>
            </div>
            <button type="button" onclick="closeAssignModal()" class="custom-modal-close" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.schedules.assign-employee') }}" class="panel-form">
            @csrf
            <div class="form-group">
                <label for="assign_emp_id">Select Employee *</label>
                <select id="assign_emp_id" name="employee_id" required>
                    <option value="">Choose employee...</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">
                            {{ $emp->full_name }} ({{ $emp->employee_id }}) — Current: {{ $emp->schedule->name ?? 'None' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="assign_sched_id">Select Schedule *</label>
                <select id="assign_sched_id" name="schedule_id" required>
                    <option value="">Choose schedule template...</option>
                    @foreach($schedules as $sched)
                        <option value="{{ $sched->id }}">
                            {{ $sched->name }} ({{ \Carbon\Carbon::parse($sched->am_time_in)->format('g:i A') }}–{{ \Carbon\Carbon::parse($sched->am_time_out)->format('g:i A') }} / {{ \Carbon\Carbon::parse($sched->pm_time_in)->format('g:i A') }}–{{ \Carbon\Carbon::parse($sched->pm_time_out)->format('g:i A') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="assign_eff_date">Effective Date *</label>
                <input type="date" id="assign_eff_date" name="effective_date" value="{{ \Carbon\Carbon::today('Asia/Manila')->toDateString() }}" required>
                <small class="form-help-text">Historical records before this date remain protected and retain their original schedule.</small>
            </div>

            <div class="form-group">
                <label for="assign_notes">Assignment Notes</label>
                <input type="text" id="assign_notes" name="notes" placeholder="e.g. Approved shift adjustment for Q4">
            </div>

            <div class="form-actions-row">
                <button type="button" onclick="closeAssignModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Apply Assignment</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Create Custom Schedule Exclusively for One Employee -->
<div id="customScheduleModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <div>
                <h3>Create Custom Individual Schedule</h3>
                <span class="custom-modal-subtitle">Assigned strictly to the chosen employee only</span>
            </div>
            <button type="button" onclick="closeCustomModal()" class="custom-modal-close" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>

        <form id="customScheduleForm" method="POST" action="" class="panel-form">
            @csrf
            <div class="form-group">
                <label for="custom_emp_select">Target Employee *</label>
                <select id="custom_emp_select" required onchange="updateCustomFormAction(this.value)">
                    <option value="">Select individual employee...</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="custom_name">Schedule Name *</label>
                <input type="text" id="custom_name" name="name" required placeholder="e.g. Custom Shift - [Employee]">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="custom_am_in">AM Time In *</label>
                    <input type="time" id="custom_am_in" name="am_time_in" value="07:30" required>
                </div>
                <div class="form-group">
                    <label for="custom_am_out">AM Time Out *</label>
                    <input type="time" id="custom_am_out" name="am_time_out" value="12:00" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="custom_pm_in">PM Time In *</label>
                    <input type="time" id="custom_pm_in" name="pm_time_in" value="13:00" required>
                </div>
                <div class="form-group">
                    <label for="custom_pm_out">PM Time Out *</label>
                    <input type="time" id="custom_pm_out" name="pm_time_out" value="16:30" required>
                </div>
            </div>

            <div class="form-group">
                <label for="custom_eff_date">Effective Date *</label>
                <input type="date" id="custom_eff_date" name="effective_date" value="{{ \Carbon\Carbon::today('Asia/Manila')->toDateString() }}" required>
            </div>

            <div class="form-group">
                <label for="custom_notes">Notes</label>
                <input type="text" id="custom_notes" name="notes" placeholder="Reason for custom individual shift">
            </div>

            <div class="form-actions-row">
                <button type="button" onclick="closeCustomModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Create &amp; Assign</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditScheduleModal(sched) {
    const modal = document.getElementById('editScheduleModal');
    const form = document.getElementById('editScheduleForm');
    form.action = `/admin/schedules/${sched.id}`;

    document.getElementById('edit_sched_name').value = sched.name;
    document.getElementById('edit_sched_am_in').value = (sched.am_time_in || '').substring(0, 5);
    document.getElementById('edit_sched_am_out').value = (sched.am_time_out || '').substring(0, 5);
    document.getElementById('edit_sched_pm_in').value = (sched.pm_time_in || '').substring(0, 5);
    document.getElementById('edit_sched_pm_out').value = (sched.pm_time_out || '').substring(0, 5);
    document.getElementById('edit_sched_late_am').value = (sched.late_threshold_am || '').substring(0, 5);
    document.getElementById('edit_sched_late_pm').value = (sched.late_threshold_pm || '').substring(0, 5);
    document.getElementById('edit_sched_desc').value = sched.description || '';
    document.getElementById('edit_sched_default').checked = Boolean(sched.is_default);

    modal.style.display = 'flex';
}

function closeEditScheduleModal() {
    document.getElementById('editScheduleModal').style.display = 'none';
}

function openAssignModal() {
    document.getElementById('assignScheduleModal').style.display = 'flex';
}

function closeAssignModal() {
    document.getElementById('assignScheduleModal').style.display = 'none';
}

function openCustomModal() {
    document.getElementById('customScheduleModal').style.display = 'flex';
}

function closeCustomModal() {
    document.getElementById('customScheduleModal').style.display = 'none';
}

function updateCustomFormAction(employeeId) {
    const form = document.getElementById('customScheduleForm');
    if (employeeId) {
        form.action = `/admin/schedules/custom-for-employee/${employeeId}`;
    } else {
        form.action = '';
    }
}
</script>
@endsection
