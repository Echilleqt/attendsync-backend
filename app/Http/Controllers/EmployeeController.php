<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display employees.
     */
    public function index()
    {
        $employees = Employee::with('user')
            ->latest()
            ->get();

        return view('admin.employees.index', [
            'employees' => $employees,
        ]);
    }

    /**
     * Show create employee page.
     */
    public function create()
    {
        return view('admin.employees.create');
    }

    /**
     * Store a new employee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                'unique:employees,employee_id',
            ],

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'position' => [
                'required',
                'string',
                'max:255',
            ],

            'department' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_hired' => [
                'nullable',
                'date',
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim(
                    $validated['first_name'] . ' ' .
                    $validated['last_name']
                ),

                'email' => $validated['email'],

                'password' => Hash::make(
                    $validated['password']
                ),

                'role' => 'employee',
            ]);

            Employee::create([
                'user_id' => $user->id,

                'employee_id' => $validated['employee_id'],

                'first_name' => $validated['first_name'],

                'middle_name' => $validated['middle_name'] ?? null,

                'last_name' => $validated['last_name'],

                'position' => $validated['position'],

                'department' => $validated['department'] ?? null,

                'date_hired' => $validated['date_hired'] ?? null,

                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('admin.employees.index')
            ->with(
                'success',
                'Employee account created successfully.'
            );
    }

    /**
     * Show edit employee page.
     */
    public function edit(string $employee)
    {
        $employeeRecord = Employee::with('user')
            ->findOrFail($employee);

        return view('admin.employees.edit', [
            'employee' => $employeeRecord,
        ]);
    }

    /**
     * Update an employee.
     *
     * An inactive employee cannot be reactivated.
     */
    public function update(
        Request $request,
        string $employee
    ) {
        $employeeRecord = Employee::with('user')
            ->findOrFail($employee);

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'employees',
                    'employee_id'
                )->ignore($employeeRecord->id),
            ],

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($employeeRecord->user_id),
            ],

            'position' => [
                'required',
                'string',
                'max:255',
            ],

            'department' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_hired' => [
                'nullable',
                'date',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $employeeRecord
        ) {
            $employeeRecord->update([
                'employee_id' => $validated['employee_id'],

                'first_name' => $validated['first_name'],

                'middle_name' => $validated['middle_name'] ?? null,

                'last_name' => $validated['last_name'],

                'position' => $validated['position'],

                'department' => $validated['department'] ?? null,

                'date_hired' => $validated['date_hired'] ?? null,

                // Preserve the existing status.
                // An inactive employee remains inactive.
                'status' => $employeeRecord->status,
            ]);

            $employeeRecord->user->update([
                'name' => trim(
                    $validated['first_name'] . ' ' .
                    $validated['last_name']
                ),

                'email' => $validated['email'],
            ]);
        });

        return redirect()
            ->route('admin.employees.index')
            ->with(
                'success',
                'Employee updated successfully.'
            );
    }

    /**
     * Deactivate an active employee.
     */
    public function deactivate(string $employee)
    {
        $employeeRecord = Employee::findOrFail($employee);

        if ($employeeRecord->status === 'inactive') {
            return redirect()
                ->route('admin.employees.index')
                ->with(
                    'error',
                    'This employee is already inactive.'
                );
        }

        $employeeRecord->update([
            'status' => 'inactive',
        ]);

        return redirect()
            ->route('admin.employees.index')
            ->with(
                'success',
                'Employee deactivated successfully.'
            );
    }

    /**
     * Activate an inactive employee.
     */
    public function activate(string $employee)
    {
        $employeeRecord = Employee::findOrFail($employee);

        if ($employeeRecord->status === 'active') {
            return redirect()
                ->route('admin.employees.index')
                ->with(
                    'error',
                    'This employee is already active.'
                );
        }

        $employeeRecord->update([
            'status' => 'active',
        ]);

        return redirect()
            ->route('admin.employees.index')
            ->with(
                'success',
                'Employee activated successfully.'
            );
    }

    /**
     * Permanently delete an employee and user account.
     */
    public function destroy(string $employee)
    {
        $employeeRecord = Employee::with('user')
            ->findOrFail($employee);

        DB::transaction(function () use ($employeeRecord) {
            $user = $employeeRecord->user;

            $employeeRecord->delete();

            if ($user) {
                $user->delete();
            }
        });

        return redirect()
            ->route('admin.employees.index')
            ->with(
                'success',
                'Employee permanently deleted.'
            );
    }
}