
@extends('layouts.admin')

@section('title', 'Edit Employee | ATTENDSYNC')

@section('page_title', 'Edit Employee')

@section('page_description', 'Update employee information for Bansud Central School.')

@section('content')

<section class="employee-page">

    <div class="employee-heading">

        <div>
            <span class="school-label">
                BANSUD CENTRAL SCHOOL
            </span>

            <h2>
                Edit Employee
            </h2>

            <p>
                Update the employee's information and account details.
            </p>
        </div>

        <a
            href="{{ route('admin.employees.index') }}"
            class="add-employee-button"
        >
            <svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h12"/></svg>
            Back to Employees
        </a>

    </div>

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

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="employee-panel">

        <form
            method="POST"
            action="{{ route('admin.employees.update', $employee->id) }}"
        >

            @csrf
            @method('PUT')

            <h3>Personal Information</h3>

            <div class="form-grid">

                <div class="form-group">

                    <label for="employee_id">
                        Employee ID
                    </label>

                    <input
                        type="text"
                        id="employee_id"
                        name="employee_id"
                        value="{{ old('employee_id', $employee->employee_id) }}"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="first_name">
                        First Name
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="{{ old('first_name', $employee->first_name) }}"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="middle_name">
                        Middle Name
                    </label>

                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        value="{{ old('middle_name', $employee->middle_name) }}"
                    >

                </div>

                <div class="form-group">

                    <label for="last_name">
                        Last Name
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="{{ old('last_name', $employee->last_name) }}"
                        required
                    >

                </div>

            </div>

            <h3>Account Information</h3>

            <div class="form-grid">

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $employee->user->email ?? '') }}"
                        required
                    >

                </div>

            </div>

            <h3>Employment Information</h3>

            <div class="form-grid">

                <div class="form-group">

                    <label for="position">
                        Position
                    </label>

                    <input
                        type="text"
                        id="position"
                        name="position"
                        value="{{ old('position', $employee->position) }}"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="department">
                        Department
                    </label>

                    <input
                        type="text"
                        id="department"
                        name="department"
                        value="{{ old('department', $employee->department) }}"
                    >

                </div>

                <div class="form-group">

                    <label for="date_hired">
                        Date Hired
                    </label>

                    <input
                        type="date"
                        id="date_hired"
                        name="date_hired"
                        value="{{ old('date_hired', $employee->date_hired?->format('Y-m-d')) }}"
                    >

                </div>

                <div class="form-group">

                    <label for="current_status">
                        Account Status
                    </label>

                    <input
                        type="text"
                        id="current_status"
                        value="{{ ucfirst($employee->status) }}"
                        disabled
                    >

                    <small>
                        Status cannot be changed here.
                        Deactivated employees cannot be reactivated.
                    </small>

                </div>

            </div>

            <div class="form-actions">

                <a
                    href="{{ route('admin.employees.index') }}"
                    class="table-action"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="add-employee-button"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</section>

@endsection
