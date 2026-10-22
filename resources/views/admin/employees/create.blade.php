@extends('layouts.admin')

@section('title', 'Add Employee - ATTENDSYNC')
@section('page_title', 'Add Employee')
@section('page_description', 'Register a new employee account, department, position, and individual schedule for Bansud Central School.')

@section('content')
<div class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <h2>Add New Employee</h2>
            <p>Create an employee account, assign organizational role, and configure individual work schedule.</p>
        </div>

        <a href="{{ route('admin.employees.index') }}" class="btn-secondary">
            <x-lucide-arrow-left class="w-4 h-4" aria-hidden="true" />
            <span>Back to Employees</span>
        </a>
    </div>

    <div class="employee-panel">
        <form method="POST" action="{{ route('admin.employees.store') }}" class="panel-form">
            @csrf

            <div class="form-section-title">Personal Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="employee_id">Employee ID *</label>
                    <input
                        type="text"
                        id="employee_id"
                        name="employee_id"
                        value="{{ old('employee_id') }}"
                        placeholder="e.g. EMP-1002"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="{{ old('first_name') }}"
                        placeholder="Enter first name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        value="{{ old('middle_name') }}"
                        placeholder="Enter middle name (optional)"
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="{{ old('last_name') }}"
                        placeholder="Enter last name"
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
                        value="{{ old('email') }}"
                        placeholder="employee@bansud.deped.gov.ph"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Initial Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password *</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Re-type password"
                        minlength="8"
                        required
                    >
                </div>
            </div>

            <div class="form-section-title mt-6">Organizational Role &amp; Schedule Assignment</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="department_select">Department *</label>
                    <select id="department_select" name="department_id" required onchange="onDepartmentChange(this.value)">
                        <option value="">Select Department...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>
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
                                @selected(old('position_id') == $pos->id)
                            >
                                {{ $pos->name }} ({{ $pos->department->name ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="schedule_select">Individual Work Schedule</label>
                    <select id="schedule_select" name="schedule_id">
                        <option value="">Select Schedule Shift...</option>
                        @foreach($schedules as $sched)
                            <option value="{{ $sched->id }}" @selected(old('schedule_id', $sched->is_default ? $sched->id : '') == $sched->id)>
                                {{ $sched->name }} ({{ \Carbon\Carbon::parse($sched->am_time_in)->format('g:i A') }}–{{ \Carbon\Carbon::parse($sched->pm_time_out)->format('g:i A') }})
                                {{ $sched->is_default ? '— [Default]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_hired">Date Hired / Effective Date</label>
                    <input
                        type="date"
                        id="date_hired"
                        name="date_hired"
                        value="{{ old('date_hired', \Carbon\Carbon::today('Asia/Manila')->toDateString()) }}"
                    >
                </div>

                <div class="form-group">
                    <label for="status">Employment Status</label>
                    <select id="status" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-actions-row mt-6">
                <a href="{{ route('admin.employees.index') }}" class="btn-secondary">
                    Cancel
                </a>

                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Save Employee</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const allPositions = @json($positions);

function onDepartmentChange(departmentId) {
    const posSelect = document.getElementById('position_select');
    const currentVal = posSelect.value;
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
        if (String(currentVal) === String(p.id)) {
            opt.selected = true;
        }
        posSelect.appendChild(opt);
    });
}

// Initial filter if department already selected
document.addEventListener('DOMContentLoaded', () => {
    const deptSelect = document.getElementById('department_select');
    if (deptSelect && deptSelect.value) {
        onDepartmentChange(deptSelect.value);
    }
});
</script>
@endsection