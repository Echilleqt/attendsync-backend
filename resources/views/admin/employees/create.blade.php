
@extends('layouts.admin')

@section('title', 'Add Employee - ATTENDSYNC')

@section('page_title', 'Add Employee')

@section('page_description', 'Register a new employee account for Bansud Central School.')

@section('content')

<div class="employee-page">

    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <h2>Add New Employee</h2>
            <p>Create an employee account and employment profile.</p>
        </div>

        <a href="{{ route('admin.employees.index') }}" class="add-employee-button">
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

    <div class="employee-panel">

        <form
            method="POST"
            action="{{ route('admin.employees.store') }}"
        >
            @csrf

            <h3>Personal Information</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label for="employee_id">Employee ID</label>
                    <input
                        type="text"
                        id="employee_id"
                        name="employee_id"
                        value="{{ old('employee_id') }}"
                        placeholder="e.g. EMP-0002"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="first_name">First Name</label>
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
                        placeholder="Enter middle name"
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
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

            <h3>Account Information</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="employee@example.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Temporary Password</label>
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
                    <label for="password_confirmation">Confirm Password</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Confirm password"
                        minlength="8"
                        required
                    >
                </div>

            </div>

            <h3>Employment Information</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label for="position">Position</label>
                    <input
                        type="text"
                        id="position"
                        name="position"
                        value="{{ old('position') }}"
                        placeholder="e.g. Teacher"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input
                        type="text"
                        id="department"
                        name="department"
                        value="{{ old('department') }}"
                        placeholder="e.g. Teaching"
                    >
                </div>

                <div class="form-group">
                    <label for="date_hired">Date Hired</label>
                    <input
                        type="date"
                        id="date_hired"
                        name="date_hired"
                        value="{{ old('date_hired') }}"
                    >
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
                    Save Employee
                </button>
            </div>

        </form>

    </div>

</div>

@endsection