@extends('layouts.admin')

@section('title', 'Departments — ATTENDSYNC')
@section('page_title', 'Department Management')
@section('page_description', 'Organize faculty and staff into school grade levels and administrative offices.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Organize faculty and staff into school departments and offices.</p>
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
        <div class="employee-panel panel-fill">
            <div class="employee-panel-header">
                <div>
                    <h3>Registered Departments ({{ $departments->total() }})</h3>
                    <p>School academic and administrative units</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Assigned Personnel</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $dept)
                        <tr>
                            <td><strong class="font-mono text-slate-800">{{ $dept->code }}</strong></td>
                            <td>
                                <strong>{{ $dept->name }}</strong>
                                @if($dept->description)
                                    <div class="table-meta-line">{{ $dept->description }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <x-lucide-users class="w-3 h-3" aria-hidden="true" />
                                    <span>{{ $dept->employees_count }} employees</span>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="employee-actions">
                                    <button
                                        type="button"
                                        class="table-action edit-action"
                                        onclick="openEditDeptModal({{ json_encode($dept) }})"
                                        title="Edit department"
                                    >
                                        <x-lucide-pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                        <span>Edit</span>
                                    </button>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.departments.destroy', $dept) }}"
                                        style="display:inline;"
                                        data-confirm-message="Are you sure you want to delete department '{{ $dept->name }}'? Deletion is blocked if employees or positions are still assigned."
                                        data-confirm-title="Delete Department"
                                        data-confirm-type="danger"
                                        data-confirm-btn="Delete"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="table-action delete-action"
                                            {{ $dept->employees_count > 0 ? 'disabled title="Cannot delete department with assigned employees"' : '' }}
                                            style="{{ $dept->employees_count > 0 ? 'opacity: 0.5; cursor: not-allowed;' : '' }}"
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
                                    <x-lucide-building-2 class="w-8 h-8 text-slate-400" />
                                </div>
                                <h4>No departments registered yet</h4>
                                <p>Use the form on the right to register departments (e.g. Teaching Department, Administrative Department).</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="request-pagination">
                {{ $departments->links() }}
            </div>
        </div>

        <div class="employee-panel panel-sidebar">
            <div class="employee-panel-header panel-sidebar-header">
                <div>
                    <h3>Add New Department</h3>
                    <p>Create academic or admin unit</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.departments.store') }}" class="panel-form">
                @csrf
                <div class="form-group">
                    <label for="new_dept_code">Department Code *</label>
                    <input type="text" id="new_dept_code" name="code" value="{{ old('code') }}" required placeholder="e.g. TEACH, ADMIN, GRADE-1">
                </div>

                <div class="form-group">
                    <label for="new_dept_name">Department Name *</label>
                    <input type="text" id="new_dept_name" name="name" value="{{ old('name') }}" required placeholder="e.g. Teaching Department">
                </div>

                <div class="form-group">
                    <label for="new_dept_description">Description</label>
                    <textarea id="new_dept_description" name="description" rows="3" placeholder="Optional notes or mandate">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full">
                    <x-lucide-plus class="w-4 h-4" aria-hidden="true" />
                    <span>Create Department</span>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Edit Department Modal -->
<div id="editDeptModal" class="custom-modal-backdrop" style="display: none;">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h3>Edit Department</h3>
            <button type="button" onclick="closeEditDeptModal()" class="custom-modal-close" aria-label="Close dialog">
                <x-lucide-x class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>

        <form id="editDeptForm" method="POST" action="" class="panel-form">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="edit_dept_code">Department Code *</label>
                <input type="text" id="edit_dept_code" name="code" required>
            </div>

            <div class="form-group">
                <label for="edit_dept_name">Department Name *</label>
                <input type="text" id="edit_dept_name" name="name" required>
            </div>

            <div class="form-group">
                <label for="edit_dept_description">Description</label>
                <textarea id="edit_dept_description" name="description" rows="3"></textarea>
            </div>

            <div class="form-actions-row">
                <button type="button" onclick="closeEditDeptModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <x-lucide-circle-check class="w-4 h-4" aria-hidden="true" />
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDeptModal(dept) {
    const modal = document.getElementById('editDeptModal');
    const form = document.getElementById('editDeptForm');
    form.action = `/admin/departments/${dept.id}`;
    document.getElementById('edit_dept_code').value = dept.code;
    document.getElementById('edit_dept_name').value = dept.name;
    document.getElementById('edit_dept_description').value = dept.description || '';
    modal.style.display = 'flex';
}

function closeEditDeptModal() {
    document.getElementById('editDeptModal').style.display = 'none';
}
</script>
@endsection
