<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceSetting;
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

        if (
            ($correction->request_type === 'missing_time_in' && $attendance?->time_in)
            || ($correction->request_type === 'missing_time_out' && $attendance?->time_out)
        ) {
            throw ValidationException::withMessages([
                'attendance' => 'The attendance event is no longer missing; review the record before approving.',
            ]);
        }

        $original = [
            'attendance_id' => $attendance?->id,
            'time_in' => $attendance?->time_in?->toDateTimeString(),
            'time_out' => $attendance?->time_out?->toDateTimeString(),
            'status' => $attendance?->status,
            'am_status' => $attendance?->am_status,
            'pm_status' => $attendance?->pm_status,
            'late_starting_time_snapshot' => $attendance?->late_starting_time_snapshot,
        ];

        $date = $correction->attendance_date->format('Y-m-d');
        $timeIn = $correction->requested_time_in
            ? (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $correction->requested_time_in)
                ? Carbon::parse($correction->requested_time_in, config('app.timezone'))
                : Carbon::parse("$date {$correction->requested_time_in}", config('app.timezone')))
            : $attendance?->time_in;
        $timeOut = $correction->requested_time_out
            ? (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $correction->requested_time_out)
                ? Carbon::parse($correction->requested_time_out, config('app.timezone'))
                : Carbon::parse("$date {$correction->requested_time_out}", config('app.timezone')))
            : $attendance?->time_out;

        if ($timeIn && $timeOut && $timeOut->lessThanOrEqualTo($timeIn)) {
            throw ValidationException::withMessages([
                'requested_time_out' => 'The approved Time Out must be later than Time In.',
            ]);
        }

        $attendance ??= new Attendance();
        $attendance->employee_id = $correction->employee_id;
        $attendance->attendance_date = $date;

        $ruleService = app(AttendanceRuleService::class);
        $employee = $correction->employee;
        $schedule = $employee ? $ruleService->resolveScheduleForEmployeeAndDate($employee, $date) : null;

        if ($schedule) {
            $attendance->schedule_id = $schedule->id;
            $times = $ruleService->getScheduleTimes($schedule, $date);

            if ($correction->requested_time_in) {
                if ($timeIn->format('H:i:s') <= $times['am_time_out']->format('H:i:s')) {
                    $attendance->am_time_in = $timeIn;
                    $attendance->time_in = $timeIn;
                } else {
                    $attendance->pm_time_in = $timeIn;
                    if (empty($attendance->time_in)) {
                        $attendance->time_in = $timeIn;
                    }
                }
            } elseif ($timeIn) {
                $attendance->time_in = $timeIn;
                $attendance->am_time_in ??= $timeIn;
            }

            if ($correction->requested_time_out) {
                if ($timeOut->format('H:i:s') >= $times['pm_time_in']->format('H:i:s')) {
                    $attendance->pm_time_out = $timeOut;
                    $attendance->time_out = $timeOut;
                } else {
                    $attendance->am_time_out = $timeOut;
                    if (empty($attendance->time_out)) {
                        $attendance->time_out = $timeOut;
                    }
                }
            } elseif ($timeOut) {
                $attendance->time_out = $timeOut;
                $attendance->pm_time_out ??= $timeOut;
            }

            $endOfDay = Carbon::parse($date, 'Asia/Manila')->endOfDay();
            $amStatus = $ruleService->evaluateAmStatus($attendance->am_time_in, $attendance->am_time_out, $schedule, $date, $endOfDay);
            $pmStatus = $ruleService->evaluatePmStatus($attendance->pm_time_in, $attendance->pm_time_out, $schedule, $date, $endOfDay);

            $attendance->am_status = $amStatus;
            $attendance->pm_status = $pmStatus;
            $attendance->status = $ruleService->computeOverallStatus($amStatus, $pmStatus);
            $attendance->late_starting_time_snapshot = $times['late_cutoff_am']?->format('H:i:s');
            $attendance->undertime_minutes = $ruleService->calculateUndertime($attendance, $schedule, $date);
        } else {
            $threshold = $attendance->late_starting_time_snapshot;
            if ($timeIn && $threshold === null) {
                $setting = AttendanceSetting::query()
                    ->whereDate('effective_date', '<=', $date)
                    ->orderByDesc('effective_date')
                    ->first();
                $threshold = $setting?->late_starting_time;
            }

            $status = $timeIn && $threshold
                ? ($timeIn->format('H:i:s') > Carbon::parse($threshold)->format('H:i:s') ? 'late' : 'present')
                : ($attendance->status ?? 'present');

            $attendance->time_in = $timeIn;
            $attendance->time_out = $timeOut;
            $attendance->late_starting_time_snapshot = $threshold;
            $attendance->status = $status;
        }

        $attendance->save();

        $correction->original_values = $original;
        $correction->approved_values = [
            'attendance_id' => $attendance->id,
            'time_in' => $attendance->time_in?->toDateTimeString(),
            'time_out' => $attendance->time_out?->toDateTimeString(),
            'am_status' => $attendance->am_status,
            'pm_status' => $attendance->pm_status,
            'status' => $attendance->status,
            'late_starting_time_snapshot' => $attendance->late_starting_time_snapshot,
            'reason' => $correction->reason,
        ];
    }
}
