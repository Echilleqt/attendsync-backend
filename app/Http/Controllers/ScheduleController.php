<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        $schedules = Schedule::withCount('employees')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);

        $employees = Employee::where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.schedules.index', compact('schedules', 'employees'));
    }

    public function show(Schedule $schedule): View
    {
        $schedule->load(['employees' => function ($q) {
            $q->orderBy('last_name')->orderBy('first_name');
        }]);

        $attendanceCount = $schedule->attendances()->count();
        $historicalAssignmentCount = $schedule->assignments()->count();

        return view('admin.schedules.show', compact('schedule', 'attendanceCount', 'historicalAssignmentCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:schedules,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'am_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'am_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'late_threshold_am' => ['nullable', 'date_format:H:i,H:i:s'],
            'late_threshold_pm' => ['nullable', 'date_format:H:i,H:i:s'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $this->validateTimeSequence($validated);

        if (!empty($validated['is_default'])) {
            Schedule::query()->update(['is_default' => false]);
        }

        Schedule::create([
            ...$validated,
            'late_threshold_am' => $validated['late_threshold_am'] ?? $validated['am_time_in'],
            'late_threshold_pm' => $validated['late_threshold_pm'] ?? $validated['pm_time_in'],
            'is_default' => !empty($validated['is_default']),
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 0,
            'work_days' => $validated['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', "Work schedule '{$validated['name']}' created successfully.");
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:schedules,name,' . $schedule->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'am_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'am_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'late_threshold_am' => ['nullable', 'date_format:H:i,H:i:s'],
            'late_threshold_pm' => ['nullable', 'date_format:H:i,H:i:s'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $this->validateTimeSequence($validated);

        if (!empty($validated['is_default'])) {
            Schedule::where('id', '!=', $schedule->id)->update(['is_default' => false]);
        }

        $schedule->update([
            ...$validated,
            'late_threshold_am' => $validated['late_threshold_am'] ?? $validated['am_time_in'],
            'late_threshold_pm' => $validated['late_threshold_pm'] ?? $validated['pm_time_in'],
            'is_default' => !empty($validated['is_default']),
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 0,
            'work_days' => $validated['work_days'] ?? $schedule->work_days,
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', "Work schedule '{$schedule->name}' updated successfully.");
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $assignedCount = $schedule->employees()->count();
        if ($assignedCount > 0) {
            return redirect()->route('admin.schedules.index')
                ->with('error', "Cannot delete schedule '{$schedule->name}' because {$assignedCount} employee(s) are currently assigned to it. Reassign employees first.");
        }

        $name = $schedule->name;
        $schedule->delete();

        return redirect()->route('admin.schedules.index')
            ->with('success', "Work schedule '{$name}' safely archived/deleted.");
    }

    /**
     * Assign a schedule template to a specific individual employee with an effective date.
     * Changing Employee A's schedule never changes Employee B's schedule.
     */
    public function assignToEmployee(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $schedule = Schedule::findOrFail($validated['schedule_id']);
        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();

        DB::transaction(function () use ($employee, $schedule, $effectiveDate, $validated) {
            // Close any existing active schedule assignments on or before the new effective date
            EmployeeSchedule::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where(function ($q) use ($effectiveDate) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $effectiveDate);
                })
                ->update([
                    'end_date' => Carbon::parse($effectiveDate)->subDay()->toDateString(),
                    'is_active' => false,
                ]);

            // Create new individual assignment record
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'schedule_id' => $schedule->id,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'notes' => $validated['notes'] ?? "Assigned to {$employee->full_name}",
            ]);

            // Update current active schedule reference on employee
            $employee->update(['schedule_id' => $schedule->id]);
        });

        return redirect()->back()
            ->with('success', "Schedule '{$schedule->name}' successfully assigned to {$employee->full_name} (effective {$effectiveDate}).");
    }

    /**
     * Create a custom individual schedule specifically for one employee.
     */
    public function createCustomForEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'am_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'am_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_in' => ['required', 'date_format:H:i,H:i:s'],
            'pm_time_out' => ['required', 'date_format:H:i,H:i:s'],
            'late_threshold_am' => ['nullable', 'date_format:H:i,H:i:s'],
            'late_threshold_pm' => ['nullable', 'date_format:H:i,H:i:s'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->validateTimeSequence($validated);
        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();

        DB::transaction(function () use ($employee, $validated, $effectiveDate) {
            $schedule = Schedule::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? "Custom schedule for {$employee->full_name}",
                'am_time_in' => $validated['am_time_in'],
                'am_time_out' => $validated['am_time_out'],
                'pm_time_in' => $validated['pm_time_in'],
                'pm_time_out' => $validated['pm_time_out'],
                'late_threshold_am' => $validated['late_threshold_am'] ?? $validated['am_time_in'],
                'late_threshold_pm' => $validated['late_threshold_pm'] ?? $validated['pm_time_in'],
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => false,
            ]);

            // Close prior active assignment
            EmployeeSchedule::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update([
                    'end_date' => Carbon::parse($effectiveDate)->subDay()->toDateString(),
                    'is_active' => false,
                ]);

            // Create new individual assignment
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'schedule_id' => $schedule->id,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'notes' => $validated['notes'] ?? "Custom schedule assignment",
            ]);

            $employee->update(['schedule_id' => $schedule->id]);
        });

        return redirect()->back()
            ->with('success', "Custom schedule created and assigned exclusively to {$employee->full_name}.");
    }

    /**
     * Get schedule assignment history for an employee.
     */
    public function employeeScheduleHistory(Employee $employee): JsonResponse
    {
        $assignments = $employee->scheduleAssignments()
            ->with('schedule')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json([
            'success' => true,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'current_schedule' => $employee->schedule?->name,
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Safely deactivates or ends a schedule assignment.
     */
    public function deactivateAssignment(EmployeeSchedule $assignment): RedirectResponse
    {
        $assignment->update([
            'end_date' => Carbon::today('Asia/Manila')->toDateString(),
            'is_active' => false,
        ]);

        return redirect()->back()
            ->with('success', "Schedule assignment ended successfully.");
    }

    private function validateTimeSequence(array $validated): void
    {
        $amIn = Carbon::createFromTimeString($validated['am_time_in']);
        $amOut = Carbon::createFromTimeString($validated['am_time_out']);
        $pmIn = Carbon::createFromTimeString($validated['pm_time_in']);
        $pmOut = Carbon::createFromTimeString($validated['pm_time_out']);

        if ($amOut->lessThanOrEqualTo($amIn)) {
            throw ValidationException::withMessages([
                'am_time_out' => 'Morning Time Out must be after Morning Time In.',
            ]);
        }

        if ($pmIn->lessThan($amOut)) {
            throw ValidationException::withMessages([
                'pm_time_in' => 'Afternoon Time In cannot be earlier than Morning Time Out.',
            ]);
        }

        if ($pmOut->lessThanOrEqualTo($pmIn)) {
            throw ValidationException::withMessages([
                'pm_time_out' => 'Afternoon Time Out must be after Afternoon Time In.',
            ]);
        }
    }
}
