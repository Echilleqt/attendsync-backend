<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'status' => ['nullable', 'in:present,late,absent,incomplete,on_leave'],
        ]);

        $fromDate = $validated['from_date']
            ?? Carbon::today('Asia/Manila')->startOfMonth()->format('Y-m-d');
        $toDate = $validated['to_date']
            ?? Carbon::today('Asia/Manila')->format('Y-m-d');
        $employeeId = $validated['employee_id'] ?? null;
        $departmentId = $validated['department_id'] ?? null;
        $positionId = $validated['position_id'] ?? null;
        $scheduleId = $validated['schedule_id'] ?? null;
        $status = $validated['status'] ?? null;

        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'from_date' => 'The start date must be on or before the end date.',
            ]);
        }

        $query = Attendance::with(['employee.departmentModel', 'employee.positionModel', 'schedule'])
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($positionId) {
            $query->whereHas('employee', fn ($q) => $q->where('position_id', $positionId));
        }

        if ($scheduleId) {
            $query->where(function ($q) use ($scheduleId) {
                $q->where('schedule_id', $scheduleId)
                    ->orWhereHas('employee', fn ($eq) => $eq->where('schedule_id', $scheduleId));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $attendances = $query->latest('attendance_date')
            ->latest('am_time_in')
            ->paginate(25)
            ->withQueryString();

        // Summary calculations matching filter criteria
        $summaryQuery = Attendance::query()
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($employeeId) {
            $summaryQuery->where('employee_id', $employeeId);
        }
        if ($departmentId) {
            $summaryQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }
        if ($positionId) {
            $summaryQuery->whereHas('employee', fn ($q) => $q->where('position_id', $positionId));
        }
        if ($scheduleId) {
            $summaryQuery->where(function ($q) use ($scheduleId) {
                $q->where('schedule_id', $scheduleId)
                    ->orWhereHas('employee', fn ($eq) => $eq->where('schedule_id', $scheduleId));
            });
        }

        $presentCount = (clone $summaryQuery)->where('status', 'present')->count();
        $lateCount = (clone $summaryQuery)->where('status', 'late')->count();
        $absentCount = (clone $summaryQuery)->where('status', 'absent')->count();
        $incompleteCount = (clone $summaryQuery)->where('status', 'incomplete')->count();
        $onLeaveCount = (clone $summaryQuery)->where('status', 'on_leave')->count();
        $totalRecords = $presentCount + $lateCount + $absentCount + $incompleteCount + $onLeaveCount;

        $hasDefaultSchedule = Schedule::where('is_default', true)->exists();
        $unconfiguredEmployeesCount = $hasDefaultSchedule ? 0 : Employee::where('status', 'active')
            ->whereNull('schedule_id')
            ->whereDoesntHave('scheduleAssignments', fn ($q) => $q->where('is_active', true))
            ->count();

        $employees = Employee::orderBy('last_name')->orderBy('first_name')->get();
        $departments = Department::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $schedules = Schedule::orderBy('name')->get();

        return view('admin.attendance.index', compact(
            'attendances',
            'employees',
            'departments',
            'positions',
            'schedules',
            'fromDate',
            'toDate',
            'employeeId',
            'departmentId',
            'positionId',
            'scheduleId',
            'status',
            'presentCount',
            'lateCount',
            'absentCount',
            'incompleteCount',
            'onLeaveCount',
            'totalRecords',
            'unconfiguredEmployeesCount'
        ));
    }

    /**
     * Admin view attendance photo proof for any of the 4 events.
     */
    public function photo(Request $request, Attendance $attendance, ?string $entry = null)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Unauthorized access to attendance proof.');
        }

        $photoPath = match ($entry) {
            'am-out', 'morning-out' => $attendance->am_time_out_photo_path,
            'pm-in', 'afternoon-in' => $attendance->pm_time_in_photo_path,
            'pm-out', 'afternoon-out', 'time-out' => $attendance->pm_time_out_photo_path ?? $attendance->time_out_photo_path,
            default => $attendance->am_time_in_photo_path ?? $attendance->photo_path,
        };

        if (!$photoPath || !Storage::disk('local')->exists($photoPath)) {
            abort(404, 'Attendance photo not found.');
        }

        $path = Storage::disk('local')->path($photoPath);
        $mime = mime_content_type($path);

        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            abort(415, 'Unsupported image type.');
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}