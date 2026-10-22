<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionReviewService
{
    public function review(
        int $id,
        User $reviewer,
        bool $approve,
        ?string $reviewNotes
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($id, $reviewer, $approve, $reviewNotes): AttendanceCorrectionRequest {
            $correction = AttendanceCorrectionRequest::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($correction->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'This correction request has already been reviewed.',
                ]);
            }

            if ($reviewer->employee && (int) $reviewer->employee->id === (int) $correction->employee_id) {
                throw ValidationException::withMessages([
                    'reviewer' => 'Administrators are not permitted to review or approve their own attendance correction requests.',
                ]);
            }

            if ($approve) {
                $this->applyCorrection($correction);
            }

            $correction->status = $approve ? 'approved' : 'rejected';
            $correction->admin_review_notes = $reviewNotes;
            $correction->reviewed_by = $reviewer->id;
            $correction->reviewed_at = Carbon::now(config('app.timezone'));
            $correction->save();

            return $correction->fresh(['employee.user', 'reviewer']);
        });
    }

    private function applyCorrection(AttendanceCorrectionRequest $correction): void
    {
        $attendance = Attendance::query()
            ->where('employee_id', $correction->employee_id)
            ->whereDate('attendance_date', $correction->attendance_date)
            ->lockForUpdate()
            ->first();

        $original = [
            'attendance_id' => $attendance?->id,
            'time_in' => $attendance?->time_in?->toDateTimeString(),
            'time_out' => $attendance?->time_out?->toDateTimeString(),
            'am_time_in' => $attendance?->am_time_in?->toDateTimeString(),
            'am_time_out' => $attendance?->am_time_out?->toDateTimeString(),
            'pm_time_in' => $attendance?->pm_time_in?->toDateTimeString(),
            'pm_time_out' => $attendance?->pm_time_out?->toDateTimeString(),
            'status' => $attendance?->status,
            'am_status' => $attendance?->am_status,
            'pm_status' => $attendance?->pm_status,
            'late_starting_time_snapshot' => $attendance?->late_starting_time_snapshot,
        ];

        $date = $correction->attendance_date->format('Y-m-d');
        $ruleService = app(AttendanceRuleService::class);
        $employee = $correction->employee;
        $schedule = $employee ? $ruleService->resolveScheduleForEmployeeAndDate($employee, $date) : null;

        if (!$schedule) {
            throw ValidationException::withMessages([
                'schedule' => 'This correction cannot be mapped safely because no applicable schedule is configured.',
            ]);
        }

        $timeIn = $correction->requested_time_in
            ? $this->parseRequestedTime($correction->requested_time_in, $date, 'requested_time_in')
            : null;
        $timeOut = $correction->requested_time_out
            ? $this->parseRequestedTime($correction->requested_time_out, $date, 'requested_time_out')
            : null;

        $attendance ??= new Attendance();
        $attendance->employee_id = $correction->employee_id;
        $attendance->attendance_date = $date;
        $attendance->schedule_id = $schedule->id;
        $times = $ruleService->getScheduleTimes($schedule, $date);

        if ($timeIn) {
            if (
                $attendance->time_in
                && !$attendance->am_time_in
                && !$attendance->pm_time_in
                && !$attendance->time_in->equalTo($timeIn)
            ) {
                throw ValidationException::withMessages([
                    'requested_time_in' => 'The legacy Time In conflicts with the proposed correction; review the record before approving.',
                ]);
            }

            $timeInSession = $this->sessionForTimeIn($timeIn, $times);
            $field = $timeInSession.'_time_in';
            if ($attendance->{$field}) {
                throw ValidationException::withMessages([
                    'requested_time_in' => 'The attendance event is no longer missing; review the record before approving.',
                ]);
            }
            $attendance->{$field} = $timeIn;
            $attendance->time_in ??= $timeIn;
        }

        if ($timeOut) {
            if (
                $attendance->time_out
                && !$attendance->am_time_out
                && !$attendance->pm_time_out
                && !$attendance->time_out->equalTo($timeOut)
            ) {
                throw ValidationException::withMessages([
                    'requested_time_out' => 'The legacy Time Out conflicts with the proposed correction; review the record before approving.',
                ]);
            }

            $timeOutSession = $this->sessionForTimeOut($timeOut, $times);
            $field = $timeOutSession.'_time_out';
            $inField = $timeOutSession.'_time_in';
            if ($attendance->{$field}) {
                throw ValidationException::withMessages([
                    'requested_time_out' => 'The attendance event is no longer missing; review the record before approving.',
                ]);
            }
            if (!$attendance->{$inField} || $timeOut->lessThanOrEqualTo($attendance->{$inField})) {
                throw ValidationException::withMessages([
                    'requested_time_out' => 'The approved Time Out must be later than its matching session Time In.',
                ]);
            }
            $attendance->{$field} = $timeOut;
            $attendance->time_out = $attendance->pm_time_out ?? $timeOut;
        }

        $endOfDay = Carbon::parse($date, 'Asia/Manila')->endOfDay();
        $amStatus = $ruleService->evaluateAmStatus($attendance->am_time_in, $attendance->am_time_out, $schedule, $date, $endOfDay);
        $pmStatus = $ruleService->evaluatePmStatus($attendance->pm_time_in, $attendance->pm_time_out, $schedule, $date, $endOfDay);

        $attendance->am_status = $amStatus;
        $attendance->pm_status = $pmStatus;
        $attendance->status = $ruleService->computeOverallStatus($amStatus, $pmStatus);
        $attendance->late_starting_time_snapshot = $times['late_cutoff_am']?->format('H:i:s');
        $attendance->undertime_minutes = $ruleService->calculateUndertime($attendance, $schedule, $date);
        $attendance->save();

        $correction->original_values = $original;
        $correction->approved_values = [
            'attendance_id' => $attendance->id,
            'time_in' => $attendance->time_in?->toDateTimeString(),
            'time_out' => $attendance->time_out?->toDateTimeString(),
            'am_time_in' => $attendance->am_time_in?->toDateTimeString(),
            'am_time_out' => $attendance->am_time_out?->toDateTimeString(),
            'pm_time_in' => $attendance->pm_time_in?->toDateTimeString(),
            'pm_time_out' => $attendance->pm_time_out?->toDateTimeString(),
            'am_status' => $attendance->am_status,
            'pm_status' => $attendance->pm_status,
            'status' => $attendance->status,
            'late_starting_time_snapshot' => $attendance->late_starting_time_snapshot,
            'reason' => $correction->reason,
        ];
    }

    private function sessionForTimeIn(Carbon $time, array $times): string
    {
        if (
            $time->lessThan($times['am_time_out'])
        ) {
            return 'am';
        }
        if (
            $time->greaterThanOrEqualTo($times['pm_time_in'])
            && $time->lessThanOrEqualTo($times['pm_time_out'])
        ) {
            return 'pm';
        }

        throw ValidationException::withMessages([
            'requested_time_in' => 'The approved Time In must fall within a configured work session.',
        ]);
    }

    private function sessionForTimeOut(Carbon $time, array $times): string
    {
        if ($time->greaterThan($times['am_time_in']) && $time->lessThanOrEqualTo($times['am_time_out'])) {
            return 'am';
        }
        if ($time->greaterThan($times['pm_time_in']) && $time->lessThanOrEqualTo($times['pm_time_out'])) {
            return 'pm';
        }

        throw ValidationException::withMessages([
            'requested_time_out' => 'The approved Time Out must fall within a configured work session.',
        ]);
    }

    private function parseRequestedTime(string $value, string $date, string $field): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            $time = Carbon::parse($value, 'Asia/Manila');
            if ($time->toDateString() !== $date) {
                throw ValidationException::withMessages([
                    $field => 'The approved attendance time must be on the correction request date.',
                ]);
            }

            return $time->setTimezone('Asia/Manila');
        }

        return Carbon::parse("$date $value", 'Asia/Manila');
    }
}
