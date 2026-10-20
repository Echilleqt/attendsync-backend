<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $today = Carbon::today(config('app.timezone'));
        $weekStart = $today->copy()->subDays(6);
        $totalEmployees = Employee::query()->where('status', 'active')->count();

        $todaySummary = Attendance::query()
            ->whereDate('attendance_date', $today)
            ->whereHas('employee', fn ($query) => $query->where('status', 'active'))
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_count")
            ->selectRaw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_count")
            ->selectRaw("SUM(CASE WHEN am_status = 'present' THEN 1 ELSE 0 END) as am_present_count")
            ->selectRaw("SUM(CASE WHEN am_status = 'late' THEN 1 ELSE 0 END) as am_late_count")
            ->selectRaw("SUM(CASE WHEN am_status = 'absent' THEN 1 ELSE 0 END) as am_absent_count")
            ->selectRaw("SUM(CASE WHEN am_status = 'incomplete' THEN 1 ELSE 0 END) as am_incomplete_count")
            ->selectRaw("SUM(CASE WHEN pm_status = 'present' THEN 1 ELSE 0 END) as pm_present_count")
            ->selectRaw("SUM(CASE WHEN pm_status = 'late' THEN 1 ELSE 0 END) as pm_late_count")
            ->selectRaw("SUM(CASE WHEN pm_status = 'absent' THEN 1 ELSE 0 END) as pm_absent_count")
            ->selectRaw("SUM(CASE WHEN pm_status = 'incomplete' THEN 1 ELSE 0 END) as pm_incomplete_count")
            ->first();

        $presentToday = (int) ($todaySummary->present_count ?? 0);
        $lateToday = (int) ($todaySummary->late_count ?? 0);
        $absentToday = (int) ($todaySummary->absent_count ?? 0);
        $incompleteToday = (int) ($todaySummary->incomplete_count ?? 0);
        $onLeaveToday = (int) ($todaySummary->leave_count ?? 0);

        $sessionStats = [
            'am' => [
                'present' => (int) ($todaySummary->am_present_count ?? 0),
                'late' => (int) ($todaySummary->am_late_count ?? 0),
                'absent' => (int) ($todaySummary->am_absent_count ?? 0),
                'incomplete' => (int) ($todaySummary->am_incomplete_count ?? 0),
            ],
            'pm' => [
                'present' => (int) ($todaySummary->pm_present_count ?? 0),
                'late' => (int) ($todaySummary->pm_late_count ?? 0),
                'absent' => (int) ($todaySummary->pm_absent_count ?? 0),
                'incomplete' => (int) ($todaySummary->pm_incomplete_count ?? 0),
            ],
        ];

        $attendanceTotal = $presentToday + $lateToday + $absentToday + $incompleteToday + $onLeaveToday;
        $checkedInToday = $presentToday + $lateToday + $incompleteToday;
        $attendanceRate = $totalEmployees > 0
            ? min(100, (int) round(($checkedInToday / $totalEmployees) * 100))
            : 0;
        $presentPercentage = $attendanceTotal > 0 ? (int) round(($presentToday / $attendanceTotal) * 100) : 0;
        $latePercentage = $attendanceTotal > 0 ? (int) round(($lateToday / $attendanceTotal) * 100) : 0;
        $absentPercentage = $attendanceTotal > 0 ? (int) round(($absentToday / $attendanceTotal) * 100) : 0;
        $incompletePercentage = $attendanceTotal > 0 ? (int) round(($incompleteToday / $attendanceTotal) * 100) : 0;

        $hasDefaultSchedule = \App\Models\Schedule::where('is_default', true)->exists();
        $unconfiguredEmployeesCount = $hasDefaultSchedule ? 0 : Employee::where('status', 'active')
            ->whereNull('schedule_id')
            ->whereDoesntHave('scheduleAssignments', fn ($q) => $q->where('is_active', true))
            ->count();

        $attendanceByDate = Attendance::query()
            ->whereBetween('attendance_date', [$weekStart->toDateString(), $today->toDateString()])
            ->whereHas('employee', fn ($query) => $query->where('status', 'active'))
            ->selectRaw('DATE(attendance_date) as attendance_day')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->groupByRaw('DATE(attendance_date)')
            ->get()
            ->keyBy('attendance_day');

        $weeklyAttendance = new Collection();
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->copy()->addDays($offset);
            $daySummary = $attendanceByDate->get($date->toDateString());
            $weeklyAttendance->push([
                'day' => $date->format('D'),
                'date' => $date->toDateString(),
                'present' => (int) ($daySummary->present_count ?? 0),
                'late' => (int) ($daySummary->late_count ?? 0),
            ]);
        }

        $recentAttendances = Attendance::query()
            ->with(['employee:id,employee_id,first_name,middle_name,last_name', 'schedule'])
            ->where(function ($q) {
                $q->whereNotNull('time_in')
                    ->orWhereNotNull('am_time_in')
                    ->orWhereNotNull('pm_time_in');
            })
            ->latest('updated_at')
            ->take(8)
            ->get();

       return view('admin.dashboard', compact(
            'today',
            'totalEmployees',
            'presentToday',
            'lateToday',
            'absentToday',
            'incompleteToday',
            'onLeaveToday',
            'sessionStats',
            'attendanceTotal',
            'attendanceRate',
            'checkedInToday',
            'presentPercentage',
            'latePercentage',
            'absentPercentage',
            'incompletePercentage',
            'unconfiguredEmployeesCount',
            'recentAttendances',
            'weeklyAttendance'
        ));
    }
}