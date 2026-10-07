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
            ->selectRaw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_count")
            ->first();

        $presentToday = (int) ($todaySummary->present_count ?? 0);
        $lateToday = (int) ($todaySummary->late_count ?? 0);
        $absentToday = (int) ($todaySummary->absent_count ?? 0);
        $onLeaveToday = (int) ($todaySummary->leave_count ?? 0);
        $attendanceTotal = $presentToday + $lateToday + $absentToday + $onLeaveToday;
        $checkedInToday = $presentToday + $lateToday;
        $attendanceRate = $totalEmployees > 0
            ? min(100, (int) round(($checkedInToday / $totalEmployees) * 100))
            : 0;
        $presentPercentage = $attendanceTotal > 0 ? (int) round(($presentToday / $attendanceTotal) * 100) : 0;
        $latePercentage = $attendanceTotal > 0 ? (int) round(($lateToday / $attendanceTotal) * 100) : 0;
        $absentPercentage = $attendanceTotal > 0 ? (int) round(($absentToday / $attendanceTotal) * 100) : 0;

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
            ->with('employee:id,employee_id,first_name,middle_name,last_name')
            ->whereNotNull('time_in')
            ->latest('time_in')
            ->take(8)
            ->get();

       return view('admin.dashboard', compact(
            'today',
            'totalEmployees',
            'presentToday',
            'lateToday',
            'absentToday',
            'onLeaveToday',
            'attendanceTotal',
            'attendanceRate',
            'checkedInToday',
            'presentPercentage',
            'latePercentage',
            'absentPercentage',
            'recentAttendances',
            'weeklyAttendance'
        ));
    }
}