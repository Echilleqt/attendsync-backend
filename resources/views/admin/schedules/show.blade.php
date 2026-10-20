@extends('layouts.admin')

@section('title', $schedule->name . ' — Schedule Details | ATTENDSYNC')
@section('page_title', 'Schedule Details: ' . $schedule->name)
@section('page_description', 'View schedule configuration and currently assigned school personnel.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Detailed shift specifications and active employee assignments.</p>
        </div>
        <a href="{{ route('admin.schedules.index') }}" class="btn-secondary">
            <x-lucide-arrow-left class="w-4 h-4" aria-hidden="true" />
            <span>Back to Schedules</span>
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <x-lucide-circle-check class="alert-icon" aria-hidden="true" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="admin-split-layout">
        <!-- Schedule Overview Card -->
        <div class="employee-panel panel-sidebar">
            <div class="employee-panel-header panel-sidebar-header">
                <div>
                    <h3>{{ $schedule->name }}</h3>
                    @if($schedule->is_default)
                        <span class="badge badge-success mt-1">DEFAULT TEMPLATE</span>
                    @endif
                </div>
            </div>

            @if($schedule->description)
                <p class="text-secondary text-sm mb-4">{{ $schedule->description }}</p>
            @endif

            <div class="shift-card-preview">
                <div class="shift-card-header">
                    <x-lucide-clock class="w-4 h-4 text-blue-600" aria-hidden="true" />
                    <h4>Morning Shift (AM)</h4>
                </div>
                <div class="shift-row">
                    <span class="shift-row-label">Time In:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->am_time_in)->format('h:i A') }}</strong>
                </div>
                <div class="shift-row">
                    <span class="shift-row-label">Time Out:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->am_time_out)->format('h:i A') }}</strong>
                </div>
                <div class="shift-row shift-warning">
                    <span class="shift-row-label">Late After:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->late_threshold_am)->format('h:i A') }}</strong>
                </div>
            </div>

            <div class="shift-card-preview">
                <div class="shift-card-header">
                    <x-lucide-clock class="w-4 h-4 text-blue-600" aria-hidden="true" />
                    <h4>Afternoon Shift (PM)</h4>
                </div>
                <div class="shift-row">
                    <span class="shift-row-label">Time In:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->pm_time_in)->format('h:i A') }}</strong>
                </div>
                <div class="shift-row">
                    <span class="shift-row-label">Time Out:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->pm_time_out)->format('h:i A') }}</strong>
                </div>
                <div class="shift-row shift-warning">
                    <span class="shift-row-label">Late After:</span>
                    <strong>{{ \Carbon\Carbon::parse($schedule->late_threshold_pm)->format('h:i A') }}</strong>
                </div>
            </div>

            <div class="stat-mini-grid">
                <div class="stat-mini-card">
                    <span class="stat-mini-label">Assigned</span>
                    <strong class="stat-mini-val">{{ $schedule->employees->count() }}</strong>
                </div>
                <div class="stat-mini-card">
                    <span class="stat-mini-label">Attendance Records</span>
                    <strong class="stat-mini-val">{{ $attendanceCount ?? 0 }}</strong>
                </div>
            </div>
        </div>

        <!-- Assigned Employees Table -->
        <div class="employee-panel panel-fill">
            <div class="employee-panel-header">
                <div>
                    <h3>Personnel Assigned to {{ $schedule->name }} ({{ $schedule->employees->count() }})</h3>
                    <p>Employees operating under this 4-entry schedule</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedule->employees as $emp)
                            <tr>
                                <td><strong class="font-mono text-slate-800">{{ $emp->employee_id }}</strong></td>
                                <td>
                                    <strong>{{ $emp->full_name }}</strong>
                                    <small class="table-meta-line">{{ $emp->user->email ?? '—' }}</small>
                                </td>
                                <td>{{ $emp->departmentModel->name ?? $emp->department ?? '—' }}</td>
                                <td>{{ $emp->positionModel->name ?? $emp->position }}</td>
                                <td>
                                    <span class="status-badge {{ $emp->status === 'active' ? 'status-present' : 'status-absent' }}">
                                        {{ ucfirst($emp->status) }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="employee-actions">
                                        <a href="{{ route('admin.employees.edit', $emp->id) }}" class="table-action edit-action">
                                            <x-lucide-pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                            <span>Reassign / Edit</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-employees">
                                    <div class="empty-icon" aria-hidden="true">
                                        <x-lucide-users class="w-8 h-8 text-slate-400" />
                                    </div>
                                    <h4>No employees assigned yet</h4>
                                    <p>Assign this schedule to individual employees via the Schedule Management page or Employee Edit form.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
