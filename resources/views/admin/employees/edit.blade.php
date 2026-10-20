@extends('layouts.admin')

@section('title', 'Edit Employee — ' . $employee->full_name . ' | ATTENDSYNC')
@section('page_title', 'Edit Employee')
@section('page_description', 'Update employee information, organizational position, and work schedule assignment.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <h2>Edit Employee: {{ $employee->full_name }}</h2>
            <p>Update employment details, departmental assignment, and individual schedule shift.</p>
        </div>

        <a href="{{ route('admin.employees.index') }}" class="btn-secondary">
            <x-lucide-arrow-left class="w-4 h-4" aria-hidden="true" />
            <span>Back to Employees</span>
        </a>
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

    @if (session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="employee-panel">
        <form method="POST" action="{{ route('admin.employees.update', $employee->id) }}" class="panel-form">
            @csrf
            @method('PUT')

            <div class="form-section-title">Personal Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="employee_id">Employee ID *</label>
                    <input
                        type="text"
                        id="employee_id"
                        name="employee_id"
                        value="{{ old('employee_id', $employee->employee_id) }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="{{ old('first_name', $employee->first_name) }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        value="{{ old('middle_name', $employee->middle_name) }}"
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="{{ old('last_name', $employee->last_name) }}"
                        required
                    >
                </div>
            </div>

            <div class="form-section-title mt-6">Account Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="email">Official Email Address *</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $employee->user->email ?? '') }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="status">Account Status *</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', $employee->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-section-title mt-6">Employment &amp; Organizational Role</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="department_select">Department *</label>
                    <select id="department_select" name="department_id" required onchange="onDepartmentChange(this.value)">
                        <option value="">Select Department...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected((string)old('department_id', $employee->department_id) === (string)$dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="position_select">Position *</label>
                    <select id="position_select" name="position_id" required>
                        <option value="">Choose department first...</option>
                        @foreach($positions as $pos)
                            <option
                                value="{{ $pos->id }}"
                                data-dept="{{ $pos->department_id }}"
                                @selected((string)old('position_id', $employee->position_id) === (string)$pos->id)
                            >
                                {{ $pos->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_hired">Date Hired</label>
                    <input
                        type="date"
                        id="date_hired"
                        name="date_hired"
                        value="{{ old('date_hired', $employee->date_hired?->format('Y-m-d')) }}"
                    >
                </div>
            </div>

            <div class="form-section-title mt-6">Individual Schedule Assignment</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="schedule_select">Assigned Work Schedule</label>
                    <select id="schedule_select" name="schedule_id">
                        <option value="">No Schedule Assigned</option>
                        @foreach($schedules as $sched)
                            <option value="{{ $sched->id }}" @selected((string)old('schedule_id', $employee->schedule_id) === (string)$sched->id)>
                                {{ $sched->name }} ({{ \Carbon\Carbon::parse($sched->am_time_in)->format('g:i A') }}–{{ \Carbon\Carbon::parse($sched->pm_time_out)->format('g:i A') }})
                            </option>
                        @endforeach
                    </select>
                    <small class="form-help-text">Changing this employee's schedule will never affect other employees.</small>
                </div>

                <div class="form-group">
                    <label for="effective_date">Effective Date of Schedule</label>
                    <input
                        type="date"
                        id="effective_date"
                        name="effective_date"
                        value="{{ old('effective_date', \Carbon\Carbon::today('Asia/Manila')->toDateString()) }}"
                    >
                    <small class="form-help-text">Historical attendance before this date keeps its original applicable schedule.</small>
                </div>
            </div>

            <div class="form-actions-row mt-6">
                <a href="{{ route('admin.employees.index') }}" class="btn-secondary">
                    Cancel
                </a>

                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Biometric & Device Hardware Authorization Panel --}}
    <div class="employee-panel mt-6">
        <div class="employee-panel-header">
            <div>
                <h3>Biometric Enrollment &amp; Device Authorization</h3>
                <p>Manage face biometric enrollment permission and hardware device bindings for {{ $employee->full_name }}.</p>
            </div>
        </div>

        <div style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                {{-- Face Biometric Status --}}
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                        Face Biometric Credential
                    </div>
                    @if($employee->faceBiometricCredential)
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-weight: 600; margin-bottom: 0.5rem;">
                            <x-lucide-circle-check class="w-5 h-5 text-emerald-600" />
                            <span>Enrolled &amp; Active</span>
                        </div>
                        <p style="font-size: 0.8125rem; color: #475569; margin: 0;">
                            Enrolled on {{ $employee->faceBiometricCredential->verified_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Verified' }}. Template is securely bound to this employee.
                        </p>
                    @else
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #9a3412; font-weight: 600; margin-bottom: 0.5rem;">
                            <x-lucide-triangle-alert class="w-5 h-5 text-amber-600" />
                            <span>No Face Template Enrolled</span>
                        </div>
                        <p style="font-size: 0.8125rem; color: #475569; margin: 0;">
                            This employee has not registered face biometrics yet. As an authenticated, provisioned employee, they will complete their initial face enrollment directly on the mobile app during device setup.
                        </p>
                    @endif
                </div>

                {{-- Registered Devices Overview --}}
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                        Registered Devices
                    </div>
                    @php
                        $approvedCount = $employee->approvedDevices->where('status', 'approved')->count();
                        $pendingCount = $employee->approvedDevices->where('status', 'pending')->count();
                    @endphp
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <x-lucide-smartphone class="w-5 h-5 text-slate-600" />
                        <span style="font-weight: 600; color: #1e293b;">
                            {{ $employee->approvedDevices->count() }} Device{{ $employee->approvedDevices->count() === 1 ? '' : 's' }} Registered
                        </span>
                    </div>
                    <p style="font-size: 0.8125rem; color: #475569; margin-bottom: 0.75rem;">
                        {{ $approvedCount }} Approved &bull; {{ $pendingCount }} Pending Approval
                    </p>
                    <a href="{{ route('admin.devices.index') }}" class="btn-secondary" style="font-size: 0.8125rem; padding: 0.4rem 0.8rem; display: inline-flex;">
                        <span>View All in Device Approvals &rarr;</span>
                    </a>
                </div>
            </div>

            @if($employee->approvedDevices->isNotEmpty())
                <div class="table-responsive">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Device Model &amp; OS</th>
                                <th>Device Identifier (UUID)</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employee->approvedDevices as $dev)
                                <tr>
                                    <td>
                                        <strong>{{ $dev->device_model ?: 'Android Device' }}</strong>
                                        <small class="table-meta-line">{{ $dev->device_os ?: 'OS Unspecified' }}</small>
                                    </td>
                                    <td>
                                        <code class="font-mono text-xs bg-slate-100 text-slate-800 px-2 py-1 rounded border border-slate-200">{{ $dev->device_uuid }}</code>
                                    </td>
                                    <td>
                                        @if($dev->verified_via_face)
                                            <span class="badge badge-success">Face Verified</span>
                                        @else
                                            <span class="badge badge-neutral">Manual</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($dev->status === 'approved')
                                            <span class="status-badge status-present">Approved</span>
                                        @elseif($dev->status === 'pending')
                                            <span class="status-badge status-late">Pending</span>
                                        @elseif($dev->status === 'rejected')
                                            <span class="status-badge status-absent">Rejected</span>
                                        @else
                                            <span class="status-badge status-absent">Revoked</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-xs text-secondary">
                                            {{ $dev->registered_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        @if($dev->status === 'pending')
                                            <form method="POST" action="{{ route('admin.devices.approve', $dev) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="table-action edit-action" style="color: #15803D;">Approve</button>
                                            </form>
                                        @elseif($dev->status === 'approved')
                                            <form method="POST" action="{{ route('admin.devices.revoke', $dev) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="table-action delete-action">Revoke</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.devices.approve', $dev) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="table-action edit-action">Approve</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Schedule Assignment History Panel --}}
    <div class="employee-panel mt-6">
        <div class="employee-panel-header">
            <div>
                <h3>Schedule Assignment History</h3>
                <p>Effective dates and shift changes recorded for {{ $employee->full_name }}</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Schedule</th>
                        <th>Morning (AM)</th>
                        <th>Afternoon (PM)</th>
                        <th>Effective Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employee->scheduleAssignments as $assignment)
                        <tr>
                            <td><strong>{{ $assignment->schedule->name ?? 'Custom' }}</strong></td>
                            <td>{{ $assignment->schedule ? \Carbon\Carbon::parse($assignment->schedule->am_time_in)->format('h:i A') . '–' . \Carbon\Carbon::parse($assignment->schedule->am_time_out)->format('h:i A') : '—' }}</td>
                            <td>{{ $assignment->schedule ? \Carbon\Carbon::parse($assignment->schedule->pm_time_in)->format('h:i A') . '–' . \Carbon\Carbon::parse($assignment->schedule->pm_time_out)->format('h:i A') : '—' }}</td>
                            <td>{{ $assignment->effective_date?->format('M d, Y') ?? '—' }}</td>
                            <td>{{ $assignment->end_date?->format('M d, Y') ?? 'Present' }}</td>
                            <td>
                                <span class="status-badge {{ $assignment->is_active ? 'status-present' : 'status-leave' }}">
                                    {{ $assignment->is_active ? 'Active' : 'Closed' }}
                                </span>
                            </td>
                            <td><small class="table-meta-line">{{ $assignment->notes ?? '—' }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-calendar class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No historical assignments</h4>
                                <p>This employee is currently using their initial or default schedule.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
const allPositions = @json($positions);
const currentPosId = '{{ old("position_id", $employee->position_id) }}';

function onDepartmentChange(departmentId) {
    const posSelect = document.getElementById('position_select');
    posSelect.innerHTML = '<option value="">Select Position...</option>';

    if (!departmentId) {
        posSelect.innerHTML = '<option value="">Choose department first...</option>';
        return;
    }

    const filtered = allPositions.filter(p => String(p.department_id) === String(departmentId));
    filtered.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        if (String(currentPosId) === String(p.id)) {
            opt.selected = true;
        }
        posSelect.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const deptSelect = document.getElementById('department_select');
    if (deptSelect && deptSelect.value) {
        onDepartmentChange(deptSelect.value);
    }
});
</script>
@endsection
