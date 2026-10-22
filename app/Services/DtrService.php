<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;

class DtrService
{
    public function __construct(protected AttendanceRuleService $attendanceRuleService) {}

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

        $days = [];
        $totalUndertimeMinutes = 0;
        $totalUndertimeFinalized = true;
        $monthSchedules = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $startOfMonth->copy()->day($day);
            $dateString = $currentDate->toDateString();
            $attendance = $attendances->get($dateString);

            $isWeekend = $currentDate->isWeekend();

            // Resolve schedule for this specific historical date
            $daySchedule = $attendance?->schedule
                ?? $this->attendanceRuleService->resolveScheduleForEmployeeAndDate($employee, $currentDate);

            $isWorkDay = $daySchedule
                ? $this->attendanceRuleService->isScheduledWorkDay($daySchedule, $currentDate)
                : false;

            $amIn = null;
            $amOut = null;
            $pmIn = null;
            $pmOut = null;
            $amStatus = $attendance?->am_status;
            $pmStatus = $attendance?->pm_status;
            $dayStatus = $attendance?->status ?? '';
            $needsReview = false;

            if ($daySchedule) {
                $monthSchedules[$daySchedule->id] = $daySchedule;
            }

            $undertime = null;
            $undertimeFinalized = ! $attendance;

            if ($attendance && $daySchedule) {
                $asOf = $currentDate->isSameDay($today)
                    ? Carbon::now('Asia/Manila')
                    : $currentDate->copy()->endOfDay();
                $calculatedAttendance = clone $attendance;
                $this->attendanceRuleService->recalculateAttendance($calculatedAttendance, $daySchedule, $asOf);

                $amStatus = $calculatedAttendance->am_status;
                $pmStatus = $calculatedAttendance->pm_status;
                $dayStatus = $calculatedAttendance->status;
                $undertimeFinalized = $this->attendanceRuleService->isUndertimeFinalized(
                    $calculatedAttendance,
                    $daySchedule,
                    $currentDate
                );
                $needsReview = ! $undertimeFinalized || $dayStatus === 'incomplete';

                $times = $this->attendanceRuleService->getScheduleTimes($daySchedule, $currentDate);
                $amIn = $this->attendanceRuleService->isValidSessionArrival(
                    $attendance->am_time_in,
                    $times['am_time_out'],
                    $times['date']
                ) ? $attendance->am_time_in : null;
                $amOut = $this->attendanceRuleService->isValidSessionDeparture(
                    $attendance->am_time_out,
                    $attendance->am_time_in,
                    $times['am_time_out'],
                    $times['date']
                ) ? $attendance->am_time_out : null;
                $pmIn = $this->attendanceRuleService->isValidSessionArrival(
                    $attendance->pm_time_in,
                    $times['pm_time_out'],
                    $times['date'],
                    $times['am_time_out']
                ) ? $attendance->pm_time_in : null;
                $pmOut = $this->attendanceRuleService->isValidSessionDeparture(
                    $attendance->pm_time_out,
                    $attendance->pm_time_in,
                    $times['pm_time_out'],
                    $times['date'],
                    $times['am_time_out']
                ) ? $attendance->pm_time_out : null;

                if ($undertimeFinalized) {
                    $undertime = $this->attendanceRuleService->calculateUndertime(
                        $calculatedAttendance,
                        $daySchedule,
                        $currentDate
                    );
                    $totalUndertimeMinutes += $undertime;
                } else {
                    $totalUndertimeFinalized = false;
                }
            } elseif ($attendance) {
                $undertimeFinalized = false;
                $needsReview = true;
                $totalUndertimeFinalized = false;
            }

            $isFuture = $currentDate->greaterThan($today);
            if (! $attendance && $isFuture) {
                $dayStatus = '';
            } elseif (! $attendance && ! $isWorkDay) {
                $dayStatus = $daySchedule ? ($isWeekend ? 'weekend' : 'rest_day') : '';
            }
            if ($needsReview) {
                $totalUndertimeFinalized = false;
            }

            $days[] = [
                'day' => $day,
                'day_name' => $currentDate->format('D'),
                'date' => $dateString,
                'is_weekend' => $isWeekend,
                'is_work_day' => $isWorkDay,
                'schedule_configured' => $daySchedule !== null,
                'schedule_name' => $daySchedule?->name,
                'am_in' => $amIn ? $amIn->timezone('Asia/Manila')->format('g:i') : (! $isWorkDay ? ($isWeekend ? 'SAT/SUN' : 'REST') : ''),
                'am_out' => $amOut ? $amOut->timezone('Asia/Manila')->format('g:i') : '',
                'pm_in' => $pmIn ? $pmIn->timezone('Asia/Manila')->format('g:i') : '',
                'pm_out' => $pmOut ? $pmOut->timezone('Asia/Manila')->format('g:i') : '',
                'undertime_hours' => $undertime !== null && $undertime >= 60 ? intdiv($undertime, 60) : '',
                'undertime_minutes' => $undertime !== null && $undertime > 0 ? ($undertime % 60 ?: '') : '',
                'status' => $dayStatus,
                'am_status' => $amStatus,
                'pm_status' => $pmStatus,
                'needs_review' => $needsReview,
                'undertime_finalized' => $undertimeFinalized,
            ];
        }

        $scheduleDescriptions = collect($monthSchedules)->map(function ($schedule) {
            return implode(' - ', [
                Carbon::parse($schedule->am_time_in)->format('g:i A'),
                Carbon::parse($schedule->am_time_out)->format('g:i A'),
            ]).' / '.implode(' - ', [
                Carbon::parse($schedule->pm_time_in)->format('g:i A'),
                Carbon::parse($schedule->pm_time_out)->format('g:i A'),
            ]);
        })->unique()->values();
        $prescribedHours = $scheduleDescriptions->count() === 1
            ? $scheduleDescriptions->first()
            : ($scheduleDescriptions->isEmpty() ? 'Not configured' : 'Varies by assigned schedule');
        $scheduleNames = collect($monthSchedules)->pluck('name')->unique()->values();

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->full_name,
                'department' => $employee->departmentModel?->name ?? $employee->department ?? 'General',
                'position' => $employee->position,
                'schedule' => $scheduleNames->count() === 1
                    ? $scheduleNames->first()
                    : ($scheduleNames->isEmpty() ? 'Not configured' : 'Varies by assigned schedule'),
            ],
            'month' => $startOfMonth->format('F'),
            'year' => $startOfMonth->format('Y'),
            'year_month' => $yearMonth,
            'prescribed_hours' => $prescribedHours,
            'days' => $days,
            'total_undertime_hours' => $totalUndertimeFinalized ? intdiv($totalUndertimeMinutes, 60) : null,
            'total_undertime_minutes' => $totalUndertimeFinalized ? $totalUndertimeMinutes % 60 : null,
            'total_undertime_finalized' => $totalUndertimeFinalized,
        ];
    }
}
