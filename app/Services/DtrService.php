<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;

class DtrService
{
    /**
     * Generate the monthly DTR record for an employee formatted for CSC Form No. 48.
     */
    public function generateMonthlyDtr(Employee $employee, string $yearMonth): array
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $yearMonth, 'Asia/Manila')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->format('Y-m-d'));

        $schedule = $employee->schedule;
        $amScheduleIn = $schedule?->am_time_in ? Carbon::parse($schedule->am_time_in)->format('g:i A') : '8:00 AM';
        $amScheduleOut = $schedule?->am_time_out ? Carbon::parse($schedule->am_time_out)->format('g:i A') : '12:00 PM';
        $pmScheduleIn = $schedule?->pm_time_in ? Carbon::parse($schedule->pm_time_in)->format('g:i A') : '1:00 PM';
        $pmScheduleOut = $schedule?->pm_time_out ? Carbon::parse($schedule->pm_time_out)->format('g:i A') : '5:00 PM';

        $days = [];
        $totalUndertimeMinutes = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $startOfMonth->copy()->day($day);
            $dateString = $currentDate->toDateString();
            $attendance = $attendances->get($dateString);

            $isWeekend = $currentDate->isWeekend();

            $amIn = $attendance?->am_time_in ?? $attendance?->time_in;
            $amOut = $attendance?->am_time_out;
            $pmIn = $attendance?->pm_time_in;
            $pmOut = $attendance?->pm_time_out ?? $attendance?->time_out;

            $undertime = (int) ($attendance?->undertime_minutes ?? 0);

            // Compute tardiness / undertime if recorded
            if ($attendance) {
                // If morning time in is after schedule
                $lateCutoffAm = Carbon::parse($dateString . ' ' . ($schedule?->late_threshold_am ?? '08:00:00'), 'Asia/Manila');
                if ($amIn && $amIn->greaterThan($lateCutoffAm)) {
                    $undertime += $lateCutoffAm->diffInMinutes($amIn);
                }

                // If afternoon time in is after 1:00 PM
                $lateCutoffPm = Carbon::parse($dateString . ' ' . ($schedule?->late_threshold_pm ?? '13:00:00'), 'Asia/Manila');
                if ($pmIn && $pmIn->greaterThan($lateCutoffPm)) {
                    $undertime += $lateCutoffPm->diffInMinutes($pmIn);
                }

                // If afternoon departure is before schedule
                $departCutoffPm = Carbon::parse($dateString . ' ' . ($schedule?->pm_time_out ?? '17:00:00'), 'Asia/Manila');
                if ($pmOut && $pmOut->lessThan($departCutoffPm)) {
                    $undertime += $pmOut->diffInMinutes($departCutoffPm);
                }
            }

            $totalUndertimeMinutes += $undertime;

            $days[] = [
                'day' => $day,
                'day_name' => $currentDate->format('D'),
                'date' => $dateString,
                'is_weekend' => $isWeekend,
                'am_in' => $amIn ? $amIn->timezone('Asia/Manila')->format('g:i') : ($isWeekend ? 'SAT/SUN' : ''),
                'am_out' => $amOut ? $amOut->timezone('Asia/Manila')->format('g:i') : '',
                'pm_in' => $pmIn ? $pmIn->timezone('Asia/Manila')->format('g:i') : '',
                'pm_out' => $pmOut ? $pmOut->timezone('Asia/Manila')->format('g:i') : '',
                'undertime_hours' => $undertime >= 60 ? intdiv($undertime, 60) : '',
                'undertime_minutes' => $undertime > 0 ? ($undertime % 60 ?: '') : '',
                'status' => $attendance?->status ?? ($isWeekend ? 'weekend' : 'absent'),
            ];
        }

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->full_name,
                'department' => $employee->departmentModel?->name ?? $employee->department ?? 'General',
                'position' => $employee->position,
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
