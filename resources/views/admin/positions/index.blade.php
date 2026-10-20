@extends('layouts.admin')

@section('title', 'Positions — ATTENDSYNC')

@section('page_title', 'Position Management')
@section('page_description', 'Manage faculty and administrative staff positions and department assignments.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Manage official job titles, roles, and departmental associations.</p>
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

    <!-- Filter Bar -->
    <div class="employee-panel report-filter-panel">
        <form method="GET" action="{{ route('admin.positions.index') }}" class="report-filter-form">
            <div class="report-filter-field filter-grow">
                <label for="dept_filter">Filter by Department</label>
                <select id="dept_filter" name="department_id" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string)$selectedDepartment === (string)$dept->id)>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if($selectedDepartment)
                <div class="report-filter-actions">
                    <a href="{{ route('admin.positions.index') }}" class="btn-secondary">
                        <x-lucide-x class="w-4 h-4" aria-hidden="true" />
                        <span>Clear Filter</span>
                    </a>
                </div>
            @endif
        </form>
    </div>

    <div class="admin-split-layout">
        <!-- Positions Table -->
        <div class="employee-panel panel-fill">
            <div class="employee-panel-header">
                <div>
                    <h3>Registered Positions ({{ $positions->total() }})</h3>
                    <p>Job designations linked to school departments</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Position Title</th>
                            <th>Department</th>
                            <th>Assigned</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $pos)
                            <tr>
                                <td>
                                    <strong>{{ $pos->name }}</strong>
                                    @if($pos->description)
                                        <div class="table-meta-line">{{ $pos->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-neutral">
                                        <x-lucide-building-2 class="w-3 h-3" aria-hidden="true" />
                                        <span>{{ $pos->department->name ?? '—' }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <x-lucide-users class="w-3 h-3" aria-hidden="true" />
                                        <span>{{ $pos->employees_count }} employees</span>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="employee-actions">
                                        <button
                                            type="button"
                                            class="table-action edit-action"
                                            onclick="openEditPositionModal({{ json_encode($pos) }})"
                                            title="Edit position"
                                        >
                                            <x-lucide-pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                            <span>Edit</span>
                                        </button>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.positions.destroy', $pos) }}"
                                            style="display: inline;"
                                            data-confirm-message="Are you sure you want to delete the position '{{ $pos->name }}'?"
                                            data-confirm-title="Delete Position"
                                            data-confirm-type="danger"
                                            data-confirm-btn="Delete"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="table-action delete-action"
                                                {{ $pos->employees_count > 0 ? 'disabled title="Cannot delete position with assigned employees"' : '' }}
                                                style="{{ $pos->employees_count > 0 ? 'opacity: 0.5; cursor: not-allowed;' : '' }}"
                                            >
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" aria-hidden="true" />
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-employees">
                                    <div class="empty-icon" aria-hidden="true">
                                        <x-lucide-briefcase class="w-8 h-8 text-slate-400" />
                                    </div>
                                    <h4>No positions found</h4>
                                    <p>Select another department or add a new position using the form.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="request-pagination">
                {{ $positions->links() }}
            </div>
        </div>

        <!-- Add Position Card -->
        <div class="employee-panel panel-sidebar">
            <div class="employee-panel-header panel-sidebar-header">
                <div>
                    <h3>Add New Position</h3>
                    <p>Create job title under a department</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.positions.store') }}" class="panel-form">
                @csrf
                <div class="form-group">
                    <label for="pos_dept_id">Department *</label>
                    <select id="pos_dept_id" name="department_id" required>
                        <option value="">Select department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id || (string)$selectedDepartment === (string)$dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="pos_name">Position Title *</label>
                    <input
                        type="text"
                        id="pos_name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        placeholder="e.g. Teacher I, Principal III"
                    >
                </div>

                <div class="form-group">
                    <label for="pos_desc">Description</label>
                    <textarea
                        id="pos_desc"
                        name="description"
                        rows="3"
                        placeholder="Optional description or responsibilities"
                    >{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full">
                    <x-lucide-plus class="w-4 h-4" aria-hidden="true" />
                    <span>Create Position</span>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Edit Position Modal -->
<div id="editPositionModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h3>Edit Position</h3>
            <button type="button" onclick="closeEditPositionModal()" class="custom-modal-close" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>

        <form id="editPositionForm" method="POST" action="" class="panel-form">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="edit_department_id">Department *</label>
                <select id="edit_department_id" name="department_id" required>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="edit_name">Position Title *</label>
                <input
                    type="text"
                    id="edit_name"
                    name="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea
                    id="edit_description"
                    name="description"
                    rows="3"
                ></textarea>
            </div>

            <div class="form-actions-row">
                <button type="button" onclick="closeEditPositionModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPositionModal(position) {
    const modal = document.getElementById('editPositionModal');
    const form = document.getElementById('editPositionForm');
    form.action = `/admin/positions/${position.id}`;
    document.getElementById('edit_department_id').value = position.department_id;
    document.getElementById('edit_name').value = position.name;
    document.getElementById('edit_description').value = position.description || '';
    modal.style.display = 'flex';
}

function closeEditPositionModal() {
    document.getElementById('editPositionModal').style.display = 'none';
}
</script>
@endsection
