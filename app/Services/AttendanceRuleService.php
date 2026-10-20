<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceRuleService
{
    /**
     * Determine the authoritative schedule for an employee on a specific date.
     * Respects historical schedule assignments (EmployeeSchedule) and direct schedule assignments.
     * Never silently substitutes the default schedule when an explicit assignment exists.
     * Returns null if no valid assigned or applicable default schedule exists.
     */
    public function resolveScheduleForEmployeeAndDate(Employee $employee, Carbon|string $date, bool $allowDefault = true): ?Schedule
    {
        $targetDate = $date instanceof Carbon ? $date->toDateString() : substr((string) $date, 0, 10);

        // 1. Explicit assignment in employee_schedules for that date
        $assignment = $employee->scheduleAssignments()
            ->where('effective_date', '<=', $targetDate)
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $targetDate);
            })
            ->orderByDesc('effective_date')
            ->first();

        if ($assignment?->schedule) {
            return $assignment->schedule;
        }

        // 2. Direct explicit schedule_id on employee
        if ($employee->schedule) {
            return $employee->schedule;
        }

        if ($employee->schedule_id) {
            $sched = Schedule::find($employee->schedule_id);
            if ($sched) {
                return $sched;
            }
        }

        // 3. Fallback to default schedule only if no explicit assignment exists and default is allowed
        if ($allowDefault) {
            $default = Schedule::where('is_default', true)->first();
            if ($default) {
                return $default;
            }

            // If schedule templates exist in DB, but none is default, do not invent one
            if (Schedule::exists()) {
                return null;
            }

            // Backward compatibility for legacy tests/records where schedules table is completely empty
            $legacySetting = \App\Models\AttendanceSetting::where('effective_date', '<=', $targetDate)
                ->orderByDesc('effective_date')
                ->first() ?? \App\Models\AttendanceSetting::first();

            $lateTime = $legacySetting?->late_starting_time
                ? Carbon::parse($legacySetting->late_starting_time)->format('H:i:s')
                : null;

            return new Schedule([
                'name' => 'Default School Schedule',
                'am_time_in' => $lateTime ?? '08:00:00',
                'am_time_out' => '12:00:00',
                'pm_time_in' => '13:00:00',
                'pm_time_out' => '17:00:00',
                'late_threshold_am' => $lateTime,
                'late_threshold_pm' => '13:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'is_default' => true,
            ]);
        }

        return null;
    }

    /**
     * Check if a given date is a scheduled work day according to the schedule's work_days.
     */
    public function isScheduledWorkDay(?Schedule $schedule, Carbon|string $date): bool
    {
        if (!$schedule) {
            return false;
        }

        $dateCarbon = $date instanceof Carbon ? $date : Carbon::parse($date, 'Asia/Manila');
        $workDays = $schedule->work_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $dayName = strtolower($dateCarbon->format('l'));

        return in_array($dayName, array_map('strtolower', $workDays), true);
    }

    /**
     * Compute Carbon instances for all scheduled boundaries on a specific date in Asia/Manila.
     */
    public function getScheduleTimes(Schedule $schedule, Carbon|string $date): array
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : substr((string) $date, 0, 10);

        $amTimeIn = Carbon::parse("{$dateString} {$schedule->am_time_in}", 'Asia/Manila');
        $amTimeOut = Carbon::parse("{$dateString} {$schedule->am_time_out}", 'Asia/Manila');
        $pmTimeIn = Carbon::parse("{$dateString} {$schedule->pm_time_in}", 'Asia/Manila');
        $pmTimeOut = Carbon::parse("{$dateString} {$schedule->pm_time_out}", 'Asia/Manila');

        $baseLateAm = $schedule->late_threshold_am !== null ? $schedule->late_threshold_am : ($schedule->exists ? $schedule->am_time_in : null);
        $baseLatePm = $schedule->late_threshold_pm !== null ? $schedule->late_threshold_pm : $schedule->pm_time_in;
        $graceMinutes = (int) ($schedule->grace_period_minutes ?? 0);

        $lateCutoffAm = $baseLateAm !== null ? Carbon::parse("{$dateString} {$baseLateAm}", 'Asia/Manila')->addMinutes($graceMinutes) : null;
        $lateCutoffPm = $baseLatePm !== null ? Carbon::parse("{$dateString} {$baseLatePm}", 'Asia/Manila')->addMinutes($graceMinutes) : Carbon::parse("{$dateString} {$schedule->pm_time_in}", 'Asia/Manila')->addMinutes($graceMinutes);

        // Absence deadlines naturally align with the end of the shift session
        $amAbsenceDeadline = $amTimeOut->copy();
        $pmAbsenceDeadline = $pmTimeOut->copy();

        return [
            'date' => $dateString,
            'am_time_in' => $amTimeIn,
            'am_time_out' => $amTimeOut,
            'pm_time_in' => $pmTimeIn,
            'pm_time_out' => $pmTimeOut,
            'late_cutoff_am' => $lateCutoffAm,
            'late_cutoff_pm' => $lateCutoffPm,
            'am_absence_deadline' => $amAbsenceDeadline,
            'pm_absence_deadline' => $pmAbsenceDeadline,
            'grace_period_minutes' => $graceMinutes,
        ];
    }

    /**
     * Evaluate AM session status dynamically.
     */
    public function evaluateAmStatus(
        ?Carbon $amIn,
        ?Carbon $amOut,
        Schedule $schedule,
        Carbon|string $date,
        ?Carbon $now = null
    ): string {
        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $times = $this->getScheduleTimes($schedule, $date);

        if ($amIn) {
            $amInManila = $amIn->copy()->timezone('Asia/Manila');
            $isLate = $times['late_cutoff_am'] !== null && $amInManila->format('H:i:s') > $times['late_cutoff_am']->format('H:i:s');

            if ($amOut) {
                return $isLate ? 'late' : 'present';
            }

            // Incomplete check: valid Time In, but morning session has ended without Time Out
            if ($now->greaterThanOrEqualTo($times['am_time_out'])) {
                return 'incomplete';
            }

            return $isLate ? 'late' : 'present';
        }

        // Missing Time In: evaluate against absence deadline
        if ($now->greaterThanOrEqualTo($times['am_absence_deadline'])) {
            return 'absent';
        }

        return 'not_recorded';
    }

    /**
     * Evaluate PM session status dynamically.
     */
    public function evaluatePmStatus(
        ?Carbon $pmIn,
        ?Carbon $pmOut,
        Schedule $schedule,
        Carbon|string $date,
        ?Carbon $now = null
    ): string {
        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $times = $this->getScheduleTimes($schedule, $date);

        if ($pmIn) {
            $pmInManila = $pmIn->copy()->timezone('Asia/Manila');
            $isLate = $pmInManila->format('H:i:s') > $times['late_cutoff_pm']->format('H:i:s');

            if ($pmOut) {
                return $isLate ? 'late' : 'present';
            }

            // Incomplete check: valid Time In, but afternoon session has ended without Time Out
            if ($now->greaterThanOrEqualTo($times['pm_time_out'])) {
                return 'incomplete';
            }

            return $isLate ? 'late' : 'present';
        }

        // Missing Time In: evaluate against absence deadline
        if ($now->greaterThanOrEqualTo($times['pm_absence_deadline'])) {
            return 'absent';
        }

        return 'not_recorded';
    }

    /**
     * Compute overall whole-day attendance status from session statuses.
     */
    public function computeOverallStatus(?string $amStatus, ?string $pmStatus): string
    {
        if ($amStatus === 'on_leave' && ($pmStatus === 'on_leave' || empty($pmStatus))) {
            return 'on_leave';
        }

        if ($amStatus === 'absent' && $pmStatus === 'absent') {
            return 'absent';
        }

        if ($amStatus === 'incomplete' || $pmStatus === 'incomplete') {
            return 'incomplete';
        }

        if ($amStatus === 'late' || $pmStatus === 'late') {
            return 'late';
        }

        if ($amStatus === 'present' || $pmStatus === 'present') {
            return 'present';
        }

        if ($amStatus === 'absent' && ($pmStatus === 'not_recorded' || empty($pmStatus))) {
            return 'absent';
        }

        return 'present';
    }

    /**
     * Calculate undertime minutes for an attendance record against a schedule.
     */
    public function calculateUndertime(Attendance $attendance, Schedule $schedule, Carbon|string $date): int
    {
        $times = $this->getScheduleTimes($schedule, $date);
        $totalMinutes = 0;

        // Morning early departure
        if ($attendance->am_time_out) {
            $amOut = $attendance->am_time_out->copy()->timezone('Asia/Manila');
            if ($amOut->lessThan($times['am_time_out'])) {
                $totalMinutes += $amOut->diffInMinutes($times['am_time_out']);
            }
        }

        // Afternoon early departure
        if ($attendance->pm_time_out) {
            $pmOut = $attendance->pm_time_out->copy()->timezone('Asia/Manila');
            if ($pmOut->lessThan($times['pm_time_out'])) {
                $totalMinutes += $pmOut->diffInMinutes($times['pm_time_out']);
            }
        }

        return $totalMinutes;
    }

    /**
     * Recalculate and synchronize session statuses and daily status for an attendance record.
     */
    public function recalculateAttendance(Attendance $attendance, ?Schedule $schedule = null, ?Carbon $now = null): Attendance
    {
        $employee = $attendance->employee;
        $date = $attendance->attendance_date;
        $schedule ??= $attendance->schedule ?? ($employee ? $this->resolveScheduleForEmployeeAndDate($employee, $date) : null);

        if (!$schedule) {
            return $attendance;
        }

        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $attendance->schedule_id = $schedule->id;

        if ($attendance->status === 'on_leave') {
            $attendance->am_status = 'on_leave';
            $attendance->pm_status = 'on_leave';
            return $attendance;
        }

        $amIn = $attendance->am_time_in ?? $attendance->time_in;
        $amOut = $attendance->am_time_out;
        $pmIn = $attendance->pm_time_in;
        $pmOut = $attendance->pm_time_out ?? ($attendance->am_time_out ? $attendance->time_out : null);

        $amStatus = $this->evaluateAmStatus($amIn, $amOut, $schedule, $date, $now);
        $pmStatus = $this->evaluatePmStatus($pmIn, $pmOut, $schedule, $date, $now);

        $attendance->am_status = $amStatus;
        $attendance->pm_status = $pmStatus;
        $attendance->status = $this->computeOverallStatus($amStatus, $pmStatus);
        $attendance->undertime_minutes = $this->calculateUndertime($attendance, $schedule, $date);

        return $attendance;
    }

    /**
     * Scheduled Absence Processor:
     * Derives deadlines dynamically from each employee's assigned schedule.
     * Evaluates unrecorded sessions, marks absences, marks incomplete sessions when Time Out is missing,
     * respects leaves/holidays/rest days, is idempotent, and preserves historical data.
     */
    public function processAbsencesForDate(Carbon|string $date, ?Carbon $now = null): array
    {
        $dateCarbon = $date instanceof Carbon ? $date->copy()->timezone('Asia/Manila') : Carbon::parse($date, 'Asia/Manila');
        $dateString = $dateCarbon->toDateString();
        $now = $now ? $now->copy()->timezone('Asia/Manila') : Carbon::now('Asia/Manila');

        $stats = [
            'date' => $dateString,
            'evaluated_at' => $now->toIso8601String(),
            'total_active_employees' => 0,
            'absences_created' => 0,
            'records_updated' => 0,
            'unconfigured_skipped' => 0,
            'rest_days_skipped' => 0,
            'unconfigured_employees' => [],
        ];

        $employees = Employee::query()
            ->where('status', 'active')
            ->with(['schedule', 'scheduleAssignments.schedule'])
            ->get();

        $stats['total_active_employees'] = $employees->count();

        foreach ($employees as $employee) {
            DB::transaction(function () use ($employee, $dateCarbon, $dateString, $now, &$stats) {
                $schedule = $this->resolveScheduleForEmployeeAndDate($employee, $dateCarbon);

                // Handle missing schedule configuration explicitly
                if (!$schedule) {
                    $stats['unconfigured_skipped']++;
                    $stats['unconfigured_employees'][] = [
                        'id' => $employee->id,
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->full_name,
                    ];
                    Log::warning("ATTENDSYNC: Employee #{$employee->id} ({$employee->full_name}) has no valid work schedule assigned for {$dateString}. Absence evaluation skipped.");
                    return;
                }

                // Check if scheduled work day (skip rest days / weekends)
                if (!$this->isScheduledWorkDay($schedule, $dateCarbon)) {
                    $stats['rest_days_skipped']++;
                    return;
                }

                $times = $this->getScheduleTimes($schedule, $dateCarbon);

                $attendance = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $dateString)
                    ->lockForUpdate()
                    ->first();

                // Respect approved leaves
                if ($attendance && $attendance->status === 'on_leave') {
                    return;
                }

                $amAbsenceDue = $now->greaterThanOrEqualTo($times['am_absence_deadline']);
                $pmAbsenceDue = $now->greaterThanOrEqualTo($times['pm_absence_deadline']);

                if (!$attendance) {
                    // No attendance record exists for this employee today
                    if ($amAbsenceDue && $pmAbsenceDue) {
                        // Both morning and afternoon sessions missed
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'schedule_id' => $schedule->id,
                            'attendance_date' => $dateString,
                            'status' => 'absent',
                            'am_status' => 'absent',
                            'pm_status' => 'absent',
                            'undertime_minutes' => 0,
                            'late_starting_time_snapshot' => $times['late_cutoff_am']?->format('H:i:s'),
                        ]);
                        $stats['absences_created']++;
                    } elseif ($amAbsenceDue) {
                        // Morning session deadline passed, afternoon still pending
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'schedule_id' => $schedule->id,
                            'attendance_date' => $dateString,
                            'status' => 'absent',
                            'am_status' => 'absent',
                            'pm_status' => null,
                            'undertime_minutes' => 0,
                            'late_starting_time_snapshot' => $times['late_cutoff_am']?->format('H:i:s'),
                        ]);
                        $stats['absences_created']++;
                    }
                } else {
                    // An attendance record already exists. Synchronize session statuses safely without overwriting timestamps.
                    $attendance->schedule_id ??= $schedule->id;

                    $amIn = $attendance->am_time_in ?? $attendance->time_in;
                    $amOut = $attendance->am_time_out;
                    $pmIn = $attendance->pm_time_in;
                    $pmOut = $attendance->pm_time_out ?? ($attendance->am_time_out ? $attendance->time_out : null);

                    $origAmStatus = $attendance->am_status;
                    $origPmStatus = $attendance->pm_status;
                    $origStatus = $attendance->status;

                    $newAmStatus = $this->evaluateAmStatus($amIn, $amOut, $schedule, $dateCarbon, $now);
                    $newPmStatus = $this->evaluatePmStatus($pmIn, $pmOut, $schedule, $dateCarbon, $now);
                    $newOverallStatus = $this->computeOverallStatus($newAmStatus, $newPmStatus);

                    if (
                        $origAmStatus !== $newAmStatus ||
                        $origPmStatus !== $newPmStatus ||
                        $origStatus !== $newOverallStatus
                    ) {
                        $attendance->am_status = $newAmStatus;
                        $attendance->pm_status = $newPmStatus;
                        $attendance->status = $newOverallStatus;
                        $attendance->undertime_minutes = $this->calculateUndertime($attendance, $schedule, $dateCarbon);
                        $attendance->save();
                        $stats['records_updated']++;
                    }
                }
            });
        }

        return $stats;
    }
}
