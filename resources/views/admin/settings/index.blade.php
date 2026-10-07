
@extends('layouts.admin')

@section('title', 'Settings | ATTENDSYNC')

@section('page_title', 'Settings')

@section('page_description', 'Manage ATTENDSYNC system settings.')

@section('content')

<section class="employee-page">

    <div class="employee-heading">
        <div>
            <span class="school-label">
                BANSUD CENTRAL SCHOOL
            </span>

            <p class="employee-context">
                Manage attendance schedule settings.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="employee-panel">

        <div class="employee-panel-header">
            <div>
                <h3>Attendance Settings</h3>

                <p>
                    Set the time when an employee will be marked as late.
                </p>
            </div>
        </div>

        <div class="settings-current">

            <h4>Current Late Starting Time</h4>

            @if ($currentSetting)
                <div class="current-setting-time">
                    {{ \Carbon\Carbon::parse($currentSetting->late_starting_time)->format('h:i A') }}
                </div>

                <p>
                    Effective since:
                    {{ $currentSetting->effective_date->format('F d, Y') }}
                </p>
            @else
                <div class="current-setting-time">
                    Not configured
                </div>

                <p>
                    No attendance schedule has been configured yet.
                </p>
            @endif

        </div>

        <hr>

        <div class="settings-form">

            <h4>Add New Attendance Schedule</h4>

            <p>
                Create a new schedule without changing previous attendance
                records.
            </p>

            <form
                method="POST"
                action="{{ route('admin.settings.store') }}"
            >
                @csrf

                <div class="form-group">
                    <label for="late_starting_time">
                        Late Starting Time
                    </label>

                    <input
                        type="time"
                        id="late_starting_time"
                        name="late_starting_time"
                        value="{{ old('late_starting_time', '08:00') }}"
                        required
                    >

                    <small>
                        Employees who time in after this time will be marked
                        as late.
                    </small>
                </div>

                <div class="form-group">
                    <label for="effective_date">
                        Effective Date
                    </label>

                    <input
                        type="date"
                        id="effective_date"
                        name="effective_date"
                        value="{{ old('effective_date', now()->format('Y-m-d')) }}"
                        required
                    >

                    <small>
                        The new schedule will apply starting on this date.
                    </small>
                </div>

                <button type="submit" class="btn-primary">
                    Save Attendance Schedule
                </button>

            </form>

        </div>

    </div>

    <div class="employee-panel">

        <div class="employee-panel-header">
            <div>
                <h3>Schedule History</h3>

                <p>
                    Previously saved attendance schedules.
                </p>
            </div>
        </div>

        @if ($settings->isEmpty())

            <div class="empty-employees">
                <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="m19.3 13.7 1.1.9-1.5 2.6-1.4-.5a7.6 7.6 0 0 1-1.6.9l-.3 1.5h-3l-.3-1.5a7.6 7.6 0 0 1-1.6-.9l-1.4.5-1.5-2.6 1.1-.9a7.2 7.2 0 0 1 0-1.8l-1.1-.9 1.5-2.6 1.4.5a7.6 7.6 0 0 1 1.6-.9l.3-1.5h3l.3 1.5a7.6 7.6 0 0 1 1.6.9l1.4-.5 1.5 2.6-1.1.9a7.2 7.2 0 0 1 0 1.8Z"/></svg></div>

                <h4>No Schedule History</h4>

                <p>
                    No attendance schedules have been saved yet.
                </p>
            </div>

        @else

            <div class="table-responsive">

                <table class="employee-table">

                    <thead>
                        <tr>
                            <th>Effective Date</th>
                            <th>Late Starting Time</th>
                            <th>Created By</th>
                            <th>Created At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($settings as $setting)

                            <tr>
                                <td>
                                    {{ $setting->effective_date->format('F d, Y') }}
                                </td>

                                <td>
                                    {{ \Carbon\Carbon::parse($setting->late_starting_time)->format('h:i A') }}
                                </td>

                                <td>
                                    {{ $setting->creator?->name ?? 'System' }}
                                </td>

                                <td>
                                    {{ $setting->created_at->format('M d, Y h:i A') }}
                                </td>
                            </tr>

                        @endforeach
                    </tbody>

                </table>

            </div>

        @endif

    </div>

</section>

@endsection