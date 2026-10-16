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
            'late_starting_time_snapshot' => $attendance?->late_starting_time_snapshot,
        ];

        $date = $correction->attendance_date->format('Y-m-d');
        $timeIn = $correction->requested_time_in
            ? Carbon::parse("$date {$correction->requested_time_in}", config('app.timezone'))
            : $attendance?->time_in;
        $timeOut = $correction->requested_time_out
            ? Carbon::parse("$date {$correction->requested_time_out}", config('app.timezone'))
            : $attendance?->time_out;

        if ($timeIn && $timeOut && $timeOut->lessThanOrEqualTo($timeIn)) {
            throw ValidationException::withMessages([
                'requested_time_out' => 'The approved Time Out must be later than Time In.',
            ]);
        }

        $threshold = $attendance?->late_starting_time_snapshot;

        if ($timeIn && $threshold === null) {
            $setting = AttendanceSetting::query()
                ->whereDate('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->first();
            $threshold = $setting?->late_starting_time;
        }

        $status = $timeIn && $threshold
            ? ($timeIn->format('H:i:s') > Carbon::parse($threshold)->format('H:i:s')
                ? 'late'
                : 'present')
            : ($attendance?->status ?? 'present');

        $attendance ??= new Attendance();
        $attendance->employee_id = $correction->employee_id;
        $attendance->attendance_date = $date;
        $attendance->time_in = $timeIn;
        $attendance->time_out = $timeOut;
        $attendance->late_starting_time_snapshot = $threshold;
        $attendance->status = $status;
        $attendance->save();

        $correction->original_values = $original;
        $correction->approved_values = [
            'attendance_id' => $attendance->id,
            'time_in' => $attendance->time_in?->toDateTimeString(),
            'time_out' => $attendance->time_out?->toDateTimeString(),
            'status' => $attendance->status,
            'late_starting_time_snapshot' => $attendance->late_starting_time_snapshot,
            'reason' => $correction->reason,
        ];
    }
}
