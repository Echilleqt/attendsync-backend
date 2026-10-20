<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('employees')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', "Department '{$validated['name']}' created successfully.");
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $department->update($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', "Department '{$department->name}' updated successfully.");
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->count() > 0) {
            return redirect()->route('admin.departments.index')
                ->with('error', "Cannot delete department '{$department->name}' with assigned employees. Reassign employees first.");
        }

        if ($department->positions()->count() > 0) {
            return redirect()->route('admin.departments.index')
                ->with('error', "Cannot delete department '{$department->name}' because it still has defined positions. Remove or reassign positions first.");
        }

        $name = $department->name;
        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('success', "Department '{$name}' deleted successfully.");
    }
}
