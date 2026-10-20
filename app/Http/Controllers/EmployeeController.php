<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display employees with multi-field search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $departmentId = $request->query('department_id');
        $positionId = $request->query('position_id');
        $scheduleId = $request->query('schedule_id');
        $status = $request->query('status');

        $query = Employee::with([
            'user',
            'departmentModel',
            'positionModel',
            'schedule',
            'approvedDevices',
            'faceBiometricCredential',
            'biometricCredentials',
        ]);

        if (!empty($search)) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('employee_id', 'LIKE', $term)
                    ->orWhere('first_name', 'LIKE', $term)
                    ->orWhere('middle_name', 'LIKE', $term)
                    ->orWhere('last_name', 'LIKE', $term)
                    ->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('email', 'LIKE', $term);
                    });
            });
        }

        if (!empty($departmentId)) {
            $query->where('department_id', $departmentId);
        }

        if (!empty($positionId)) {
            $query->where('position_id', $positionId);
        }

        if (!empty($scheduleId)) {
            $query->where('schedule_id', $scheduleId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $employees = $query->orderBy('last_name')->orderBy('first_name')->paginate(15)->withQueryString();

        $departments = Department::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $schedules = Schedule::orderBy('name')->get();

        $totalCount = Employee::count();
        $activeCount = Employee::where('status', 'active')->count();
        $inactiveCount = Employee::where('status', 'inactive')->count();

        return view('admin.employees.index', compact(
            'employees',
            'departments',
            'positions',
            'schedules',
            'search',
            'departmentId',
            'positionId',
            'scheduleId',
            'status',
            'totalCount',
            'activeCount',
            'inactiveCount'
        ));
    }

    /**
     * Show create employee page with departments, positions, and schedules.
     */
    public function create(): View
    {
        $departments = Department::with('positions')->orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $schedules = Schedule::orderBy('name')->get();

        return view('admin.employees.create', compact('departments', 'positions', 'schedules'));
    }

    /**
     * Store a new employee with account and individual schedule assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                'unique:employees,employee_id',
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'date_hired' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        // Validate department-position compatibility on the backend
        $department = Department::findOrFail($validated['department_id']);
        $position = Position::where('id', $validated['position_id'])
            ->where('department_id', $department->id)
            ->first();

        if (!$position) {
            throw ValidationException::withMessages([
                'position_id' => "The selected position does not belong to the {$department->name}.",
            ]);
        }

        // If no schedule provided, pick default schedule if available
        $scheduleId = $validated['schedule_id'] ?? Schedule::where('is_default', true)->value('id');

        DB::transaction(function () use ($validated, $department, $position, $scheduleId, $request) {
            $user = User::create([
                'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'employee',
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_id' => $validated['employee_id'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'department_id' => $department->id,
                'department' => $department->name,
                'position_id' => $position->id,
                'position' => $position->name,
                'schedule_id' => $scheduleId,
                'date_hired' => $validated['date_hired'] ?? Carbon::today('Asia/Manila')->toDateString(),
                'status' => $validated['status'] ?? 'active',
            ]);

            // Create initial individual schedule assignment
            if ($scheduleId) {
                EmployeeSchedule::create([
                    'employee_id' => $employee->id,
                    'schedule_id' => $scheduleId,
                    'effective_date' => $employee->date_hired ?? Carbon::today('Asia/Manila')->toDateString(),
                    'end_date' => null,
                    'is_active' => true,
                    'notes' => 'Initial schedule assignment on employee creation.',
                ]);
            }
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Employee account for {$validated['first_name']} {$validated['last_name']} created successfully.");
    }

    /**
     * Show edit employee page.
     */
    public function edit(string $employee): View
    {
        $employeeRecord = Employee::with(['user', 'departmentModel', 'positionModel', 'schedule', 'scheduleAssignments.schedule', 'faceBiometricCredential', 'approvedDevices'])
            ->findOrFail($employee);

        $departments = Department::with('positions')->orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $schedules = Schedule::orderBy('name')->get();

        return view('admin.employees.edit', [
            'employee' => $employeeRecord,
            'departments' => $departments,
            'positions' => $positions,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Update an employee.
     */
    public function update(Request $request, string $employee): RedirectResponse
    {
        $employeeRecord = Employee::with('user')->findOrFail($employee);

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employees', 'employee_id')->ignore($employeeRecord->id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employeeRecord->user_id),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'effective_date' => ['nullable', 'date'],
            'date_hired' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Validate department-position compatibility on the backend
        $department = Department::findOrFail($validated['department_id']);
        $position = Position::where('id', $validated['position_id'])
            ->where('department_id', $department->id)
            ->first();

        if (!$position) {
            throw ValidationException::withMessages([
                'position_id' => "The selected position does not belong to the {$department->name}.",
            ]);
        }

        DB::transaction(function () use ($validated, $employeeRecord, $department, $position) {
            $newScheduleId = $validated['schedule_id'] ?? null;
            $oldScheduleId = $employeeRecord->schedule_id;

            // If schedule changed, manage individual schedule history
            if ($newScheduleId && (int) $newScheduleId !== (int) $oldScheduleId) {
                $effectiveDate = !empty($validated['effective_date'])
                    ? Carbon::parse($validated['effective_date'])->toDateString()
                    : Carbon::today('Asia/Manila')->toDateString();

                // Close previous active assignment
                EmployeeSchedule::where('employee_id', $employeeRecord->id)
                    ->where('is_active', true)
                    ->update([
                        'end_date' => Carbon::parse($effectiveDate)->subDay()->toDateString(),
                        'is_active' => false,
                    ]);

                // Record new assignment
                EmployeeSchedule::create([
                    'employee_id' => $employeeRecord->id,
                    'schedule_id' => $newScheduleId,
                    'effective_date' => $effectiveDate,
                    'end_date' => null,
                    'is_active' => true,
                    'notes' => 'Updated via Employee Management.',
                ]);
            }

            $employeeRecord->update([
                'employee_id' => $validated['employee_id'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'department_id' => $department->id,
                'department' => $department->name,
                'position_id' => $position->id,
                'position' => $position->name,
                'schedule_id' => $newScheduleId ?? $oldScheduleId,
                'date_hired' => $validated['date_hired'] ?? null,
                'status' => $validated['status'],
            ]);

            if ($employeeRecord->user) {
                $employeeRecord->user->update([
                    'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                    'email' => $validated['email'],
                ]);
            }
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Employee {$employeeRecord->full_name} updated successfully.");
    }

    /**
     * Deactivate an active employee.
     */
    public function deactivate(string $employee): RedirectResponse
    {
        $employeeRecord = Employee::findOrFail($employee);

        if ($employeeRecord->status === 'inactive') {
            return redirect()
                ->route('admin.employees.index')
                ->with('error', 'This employee is already inactive.');
        }

        $employeeRecord->update(['status' => 'inactive']);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Employee {$employeeRecord->full_name} deactivated successfully.");
    }

    /**
     * Activate an inactive employee.
     */
    public function activate(string $employee): RedirectResponse
    {
        $employeeRecord = Employee::findOrFail($employee);

        if ($employeeRecord->status === 'active') {
            return redirect()
                ->route('admin.employees.index')
                ->with('error', 'This employee is already active.');
        }

        $employeeRecord->update(['status' => 'active']);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Employee {$employeeRecord->full_name} activated successfully.");
    }

    /**
     * Safely delete / archive an employee and user account.
     * Preserves historical attendance, DTR, and correction records via soft deletes.
     */
    public function destroy(string $employee): RedirectResponse
    {
        $employeeRecord = Employee::with(['user', 'attendances'])->findOrFail($employee);
        $name = $employeeRecord->full_name;

        DB::transaction(function () use ($employeeRecord) {
            $user = $employeeRecord->user;

            // Soft-delete employee record
            $employeeRecord->delete();

            if ($user) {
                $user->delete();
            }
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Employee {$name} safely archived/deleted. Historical attendance records remain preserved.");
    }
}