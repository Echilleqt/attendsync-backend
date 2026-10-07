
@extends('layouts.admin')

@section('title', 'Employee Management | ATTENDSYNC')

@section('page_title', 'Employee Management')

@section('page_description', 'Manage school employees and personnel for ATTENDSYNC.')

@section('content')

<section class="employee-page">

    <div class="employee-heading">

    <div>
        <span class="school-label">
            BANSUD CENTRAL SCHOOL
        </span>

        <p class="employee-context">
            Manage registered school personnel and employee accounts.
        </p>
    </div>

    <a
        href="{{ route('admin.employees.create') }}"
        class="add-employee-button"
    >
        <span>+</span>
        Add Employee
    </a>

</div>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Error Messages --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Employee Summary --}}
   
<div class="employee-summary">

    <div class="summary-card">
        <div class="summary-card-content">
            <span class="summary-label">TOTAL EMPLOYEES</span>
            <h3>{{ $employees->count() }}</h3>
            <p>Registered personnel</p>
        </div>

        <div class="summary-icon total-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M17 14a4.8 4.8 0 0 1 3.5 4.6"/></svg>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-card-content">
            <span class="summary-label">ACTIVE EMPLOYEES</span>
            <h3>{{ $employees->where('status', 'active')->count() }}</h3>
            <p>Currently active</p>
        </div>

        <div class="summary-icon active-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m5 12.5 4.2 4.2L19 7"/></svg>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-card-content">
            <span class="summary-label">INACTIVE EMPLOYEES</span>
            <h3>{{ $employees->where('status', 'inactive')->count() }}</h3>
            <p>Inactive personnel</p>
        </div>

        <div class="summary-icon inactive-icon">
            <span>—</span>
        </div>
    </div>

</div>

    {{-- Employee Table --}}
    <div class="employee-panel">

        <div class="employee-panel-header">

            <div>
                <h3>Registered Employees</h3>

                <p>
                    Employee records for Bansud Central School
                </p>
            </div>

            <input
                type="text"
                class="employee-search"
                placeholder="Search employee..."
            >

        </div>

        <div class="employee-table-wrapper">

            <table class="employee-table">

                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($employees as $employee)

                        <tr>

                            <td>
                                {{ $employee->employee_id }}
                            </td>

                            <td>
                                <strong>
                                    {{ $employee->full_name }}
                                </strong>

                                <br>

                                <small>
                                    {{ $employee->user->email ?? 'No email' }}
                                </small>
                            </td>

                            <td>
                                {{ $employee->position }}
                            </td>

                            <td>
                                {{ $employee->department ?? '—' }}
                            </td>

                            <td>
                                <span
                                    class="status-badge
                                    {{ $employee->status === 'active'
                                        ? 'status-present'
                                        : 'status-absent' }}"
                                >
                                    {{ ucfirst($employee->status) }}
                                </span>
                            </td>

                            <td>

                                <div class="employee-actions">

                                    <a
                                        href="{{ route('admin.employees.edit', $employee->id) }}"
                                        class="table-action edit-action"
                                    >
                                        Edit
                                    </a>

                                    @if ($employee->status === 'active')

                                        <form
                                            method="POST"
                                            action="{{ route('admin.employees.deactivate', $employee->id) }}"
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="table-action deactivate-action"
                                                onclick="return confirm('Deactivate this employee? Their account and attendance history will be retained.')"
                                            >
                                                Deactivate
                                            </button>

                                        </form>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="empty-employees"
                            >

                                <div class="empty-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M17 14a4.8 4.8 0 0 1 3.5 4.6"/></svg>
                                </div>

                                <h4>
                                    No employee records yet
                                </h4>

                                <p>
                                    Add your first employee to start managing
                                    attendance records.
                                </p>

                                <a
                                    href="{{ route('admin.employees.create') }}"
                                    class="empty-add-button"
                                >
                                    + Add First Employee
                                </a>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</section>

<script>
    const employeeSearch = document.querySelector('.employee-search');

    employeeSearch?.addEventListener('input', () => {
        const query = employeeSearch.value.trim().toLowerCase();

        document.querySelectorAll('.employee-table tbody tr').forEach((row) => {
            row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
        });
    });
</script>

@endsection