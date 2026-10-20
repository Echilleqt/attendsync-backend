<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;

class DtrService
{
    public function __construct(protected AttendanceRuleService $attendanceRuleService)
    {
    }

    /**
     * Generate the monthly DTR record for an employee formatted for CSC Form No. 48.
     */
    public function generateMonthlyDtr(Employee $employee, string $yearMonth): array
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $yearMonth, 'Asia/Manila')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $today = Carbon::today('Asia/Manila');

        $attendances = Attendance::query()
            ->with('schedule')
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->format('Y-m-d'));

        $currentSchedule = $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($employee, $startOfMonth);
        $amScheduleIn = $currentSchedule?->am_time_in ? Carbon::parse($currentSchedule->am_time_in)->format('g:i A') : '7:30 AM';
        $amScheduleOut = $currentSchedule?->am_time_out ? Carbon::parse($currentSchedule->am_time_out)->format('g:i A') : '12:00 PM';
        $pmScheduleIn = $currentSchedule?->pm_time_in ? Carbon::parse($currentSchedule->pm_time_in)->format('g:i A') : '1:00 PM';
        $pmScheduleOut = $currentSchedule?->pm_time_out ? Carbon::parse($currentSchedule->pm_time_out)->format('g:i A') : '4:30 PM';

        $days = [];
        $totalUndertimeMinutes = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $startOfMonth->copy()->day($day);
            $dateString = $currentDate->toDateString();
            $attendance = $attendances->get($dateString);

            $isWeekend = $currentDate->isWeekend();

            // Resolve schedule for this specific historical date
            $daySchedule = $attendance?->schedule
                ?? $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($employee, $currentDate)
                ?? $currentSchedule;

            $isWorkDay = $daySchedule
                ? $this->attendanceRuleService->isScheduledWorkDay($daySchedule, $currentDate)
                : !$isWeekend;

            $amIn = $attendance?->am_time_in ?? $attendance?->time_in;
            $amOut = $attendance?->am_time_out;
            $pmIn = $attendance?->pm_time_in;
            $pmOut = $attendance?->pm_time_out ?? ($attendance?->am_time_out ? null : $attendance?->time_out);

            $undertime = 0;

            if ($attendance && $daySchedule) {
                $times = $this->attendanceRuleService->getScheduleTimes($daySchedule, $currentDate);

                // Undertime from early departure
                $undertime += $this->attendanceRuleService->calculateUndertime($attendance, $daySchedule, $currentDate);

                // Tardiness from arriving after configured late cutoff (including grace period)
                if ($amIn) {
                    $amInManila = $amIn->copy()->timezone('Asia/Manila');
                    if ($times['late_cutoff_am'] !== null && $amInManila->greaterThan($times['late_cutoff_am'])) {
                        $undertime += $times['late_cutoff_am']->diffInMinutes($amInManila);
                    }
                }

                if ($pmIn) {
                    $pmInManila = $pmIn->copy()->timezone('Asia/Manila');
                    if ($times['late_cutoff_pm'] !== null && $pmInManila->greaterThan($times['late_cutoff_pm'])) {
                        $undertime += $times['late_cutoff_pm']->diffInMinutes($pmInManila);
                    }
                }
            }

            $totalUndertimeMinutes += $undertime;

            $isFuture = $currentDate->greaterThan($today);
            $dayStatus = $attendance?->status;

            if (!$dayStatus) {
                if ($isFuture) {
                    $dayStatus = '';
                } elseif (!$isWorkDay) {
                    $dayStatus = $isWeekend ? 'weekend' : 'rest_day';
                } else {
                    $dayStatus = 'absent';
                }
            }

            $days[] = [
                'day' => $day,
                'day_name' => $currentDate->format('D'),
                'date' => $dateString,
                'is_weekend' => $isWeekend,
                'is_work_day' => $isWorkDay,
                'schedule_name' => $daySchedule?->name,
                'am_in' => $amIn ? $amIn->timezone('Asia/Manila')->format('g:i') : (!$isWorkDay ? ($isWeekend ? 'SAT/SUN' : 'REST') : ''),
                'am_out' => $amOut ? $amOut->timezone('Asia/Manila')->format('g:i') : '',
                'pm_in' => $pmIn ? $pmIn->timezone('Asia/Manila')->format('g:i') : '',
                'pm_out' => $pmOut ? $pmOut->timezone('Asia/Manila')->format('g:i') : '',
                'undertime_hours' => $undertime >= 60 ? intdiv($undertime, 60) : '',
                'undertime_minutes' => $undertime > 0 ? ($undertime % 60 ?: '') : '',
                'status' => $dayStatus,
                'am_status' => $attendance?->am_status,
                'pm_status' => $attendance?->pm_status,
            ];
        }

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->full_name,
                'department' => $employee->departmentModel?->name ?? $employee->department ?? 'General',
                'position' => $employee->position,
                'schedule' => $currentSchedule?->name ?? 'Standard',
            ],
            'month' => $startOfMonth->format('F'),
            'year' => $startOfMonth->format('Y'),
            'year_month' => $yearMonth,
            'prescribed_hours' => "{$amScheduleIn} - {$amScheduleOut} / {$pmScheduleIn} - {$pmScheduleOut}",
            'days' => $days,
            'total_undertime_hours' => intdiv($totalUndertimeMinutes, 60),
            'total_undertime_minutes' => $totalUndertimeMinutes % 60,
        ];
    }
}
