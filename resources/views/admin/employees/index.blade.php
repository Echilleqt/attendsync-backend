@extends('layouts.admin')

@section('title', 'Employee Management | ATTENDSYNC')
@section('page_title', 'Employee Management')
@section('page_description', 'Manage school employees, positions, departments, and assigned work schedules for Bansud Central School.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Manage registered school personnel, departments, positions, and individual schedules.</p>
        </div>

        <a href="{{ route('admin.employees.create') }}" class="add-employee-button">
            <x-lucide-plus class="button-icon" aria-hidden="true" />
            <span>Add Employee</span>
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            <x-lucide-circle-x class="alert-icon" aria-hidden="true" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

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

    {{-- Employee Summary Cards --}}
    <div class="employee-summary">
        <div class="summary-card">
            <div class="summary-card-content">
                <span class="summary-label">TOTAL EMPLOYEES</span>
                <h3>{{ $totalCount }}</h3>
                <p>Registered personnel</p>
            </div>
            <div class="summary-icon total-icon" aria-hidden="true">
                <x-lucide-users class="w-6 h-6" />
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-card-content">
                <span class="summary-label">ACTIVE EMPLOYEES</span>
                <h3>{{ $activeCount }}</h3>
                <p>Currently active</p>
            </div>
            <div class="summary-icon active-icon" aria-hidden="true">
                <x-lucide-circle-check class="w-6 h-6" />
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-card-content">
                <span class="summary-label">INACTIVE EMPLOYEES</span>
                <h3>{{ $inactiveCount }}</h3>
                <p>Inactive personnel</p>
            </div>
            <div class="summary-icon inactive-icon" aria-hidden="true">
                <x-lucide-circle-x class="w-6 h-6" />
            </div>
        </div>
    </div>

    {{-- Multi-field Search and Filter Bar --}}
    <div class="employee-panel report-filter-panel">
        <form method="GET" action="{{ route('admin.employees.index') }}" class="report-filter-form">
            <div class="report-filter-field filter-grow">
                <label for="search">Search Employee</label>
                <div class="input-with-icon">
                    <x-lucide-search class="input-icon" aria-hidden="true" />
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Name, Employee ID, or email..."
                    >
                </div>
            </div>

            <div class="report-filter-field">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string)$departmentId === (string)$dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field">
                <label for="position_id">Position</label>
                <select id="position_id" name="position_id">
                    <option value="">All Positions</option>
                    @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" @selected((string)$positionId === (string)$pos->id)>{{ $pos->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field">
                <label for="schedule_id">Schedule</label>
                <select id="schedule_id" name="schedule_id">
                    <option value="">All Schedules</option>
                    @foreach($schedules as $sched)
                        <option value="{{ $sched->id }}" @selected((string)$scheduleId === (string)$sched->id)>{{ $sched->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field filter-compact">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">
                    <x-lucide-list-filter class="w-4 h-4" aria-hidden="true" />
                    <span>Filter</span>
                </button>
                <a href="{{ route('admin.employees.index') }}" class="btn-secondary">
                    <span>Reset</span>
                </a>
            </div>
        </form>
    </div>

    {{-- Employee Table --}}
    <div class="employee-panel">
        <div class="employee-panel-header">
            <div>
                <h3>Registered Employees ({{ $employees->total() }})</h3>
                <p>Employee records and schedule assignments for Bansud Central School</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Department &amp; Position</th>
                        <th>Assigned Schedule</th>
                        <th>Biometric &amp; Device</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td><strong class="font-mono text-slate-800">{{ $employee->employee_id }}</strong></td>
                            <td>
                                <strong>{{ $employee->full_name }}</strong>
                                <small class="table-meta-line">{{ $employee->user->email ?? 'No email' }}</small>
                            </td>

                            <td>
                                <div><strong>{{ $employee->positionModel->name ?? $employee->position }}</strong></div>
                                <small class="table-meta-line">{{ $employee->departmentModel->name ?? $employee->department ?? '—' }}</small>
                            </td>

                            <td>
                                @if($employee->schedule)
                                    <span class="schedule-pill">
                                        <x-lucide-calendar-clock class="w-3.5 h-3.5" aria-hidden="true" />
                                        <span>{{ $employee->schedule->name }}</span>
                                    </span>
                                    <div class="table-meta-line">
                                        {{ \Carbon\Carbon::parse($employee->schedule->am_time_in)->format('g:i A') }}–{{ \Carbon\Carbon::parse($employee->schedule->pm_time_out)->format('g:i A') }}
                                    </div>
                                @else
                                    <span class="badge badge-danger">
                                        No Schedule
                                    </span>
                                @endif
                            </td>

                            <td>
                                <div class="badge-group">
                                    @if($employee->biometricCredentials->count() > 0)
                                        <span title="Fingerprint Enrolled" class="credential-badge badge-fp">
                                            <x-lucide-fingerprint class="w-3 h-3" aria-hidden="true" />
                                            <span>FP</span>
                                        </span>
                                    @endif
                                    @if($employee->faceBiometricCredential)
                                        <span title="Face Recognition Enrolled" class="credential-badge badge-face">
                                            <x-lucide-eye class="w-3 h-3" aria-hidden="true" />
                                            <span>FACE</span>
                                        </span>
                                    @endif
                                    @if($employee->approvedDevices->where('status', 'approved')->count() > 0)
                                        <span title="Approved Device Registered" class="credential-badge badge-dev">
                                            <x-lucide-smartphone class="w-3 h-3" aria-hidden="true" />
                                            <span>{{ $employee->approvedDevices->where('status', 'approved')->count() }}</span>
                                        </span>
                                    @else
                                        <span class="credential-none">No device</span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="status-badge {{ $employee->status === 'active' ? 'status-present' : 'status-absent' }}">
                                    {{ ucfirst($employee->status) }}
                                </span>
                            </td>

                            <td style="text-align: right;">
                                <div class="employee-actions">
                                    <a href="{{ route('admin.employees.edit', $employee->id) }}" class="table-action edit-action" title="Edit employee">
                                        <x-lucide-pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                        <span>Edit</span>
                                    </a>

                                    @if ($employee->status === 'active')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.employees.deactivate', $employee->id) }}"
                                            style="display: inline;"
                                            data-confirm-message="Deactivate employee '{{ $employee->full_name }}'? Their profile and historical attendance records will remain preserved."
                                            data-confirm-title="Deactivate Employee"
                                            data-confirm-type="warning"
                                            data-confirm-btn="Deactivate"
                                        >
                                            @csrf
                                            <button type="submit" class="table-action deactivate-action" title="Deactivate employee">
                                                <x-lucide-triangle-alert class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Deactivate</span>
                                            </button>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.employees.activate', $employee->id) }}"
                                            style="display: inline;"
                                            data-confirm-message="Reactivate employee '{{ $employee->full_name }}' to allow active attendance tracking?"
                                            data-confirm-title="Reactivate Employee"
                                            data-confirm-type="info"
                                            data-confirm-btn="Reactivate"
                                        >
                                            @csrf
                                            <button type="submit" class="table-action edit-action" title="Activate employee">
                                                <x-lucide-circle-check class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Activate</span>
                                            </button>
                                        </form>
                                    @endif

                                    <form
                                        method="POST"
                                        action="{{ route('admin.employees.destroy', $employee->id) }}"
                                        style="display: inline;"
                                        data-confirm-message="Archive employee '{{ $employee->full_name }}'? This will safely soft-delete the employee account while strictly preserving all historical attendance and DTR records."
                                        data-confirm-title="Archive Employee"
                                        data-confirm-type="danger"
                                        data-confirm-btn="Archive"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="table-action delete-action" title="Archive employee">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" aria-hidden="true" />
                                            <span>Archive</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-employees">
                                <div class="empty-icon" aria-hidden="true">
                                    <x-lucide-users class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No employee records found</h4>
                                <p>Adjust your search filters or add a new school employee.</p>
                                <a href="{{ route('admin.employees.create') }}" class="empty-add-button">
                                    <x-lucide-plus class="w-4 h-4" aria-hidden="true" />
                                    <span>Add First Employee</span>
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="request-pagination">
            {{ $employees->links() }}
        </div>
    </div>
</section>
@endsection