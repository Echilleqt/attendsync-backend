<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\AttendanceRuleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditAttendanceRepairsCommand extends Command
{
    protected $signature = 'attendance:audit-repairs
                            {--from= : First attendance date to inspect (Y-m-d)}
                            {--to= : Last attendance date to inspect (Y-m-d)}
                            {--employee= : Employee database ID to inspect}';

    protected $description = 'Write a read-only report of legacy attendance values that may need review.';

    public function handle(AttendanceRuleService $ruleService): int
    {
        $query = Attendance::query()->with(['employee', 'schedule'])->orderBy('id');

        if ($from = $this->option('from')) {
            $query->whereDate('attendance_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('attendance_date', '<=', $to);
        }
        if ($employeeId = $this->option('employee')) {
            $query->where('employee_id', $employeeId);
        }

        $records = [];
        $recordsInspected = (clone $query)->count();

        $query->chunkById(200, function ($attendances) use ($ruleService, &$records): void {
            foreach ($attendances as $attendance) {
                $legacyValues = [
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                ];
                $proposed = [];
                $reasons = [];
                $ambiguous = false;

                foreach ($legacyValues as $legacyField => $timestamp) {
                    if (! $timestamp) {
                        continue;
                    }

                    $timestamp = $timestamp->copy()->timezone('Asia/Manila');
                    $destination = $this->destinationFor(
                        $legacyField,
                        $timestamp,
                        $attendance,
                        $ruleService
                    );

                    if ($destination === null) {
                        $ambiguous = true;
                        $reasons[] = "Cannot uniquely map legacy {$legacyField} to a configured session.";

                        continue;
                    }

                    $existing = $attendance->{$destination};
                    if ($existing && ! $existing->equalTo($timestamp)) {
                        $ambiguous = true;
                        $reasons[] = "Legacy {$legacyField} conflicts with the existing {$destination} event.";

                        continue;
                    }

                    if (! $existing) {
                        $proposed[$destination] = $timestamp->toIso8601String();
                        $reasons[] = "Legacy {$legacyField} falls within the configured {$this->sessionLabel($destination)} session.";
                    }
                }

                if ($proposed || $ambiguous) {
                    $records[] = [
                        'attendance_id' => $attendance->id,
                        'employee_id' => $attendance->employee_id,
                        'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                        'classification' => $ambiguous ? 'needs_review' : 'candidate',
                        'original' => [
                            'time_in' => $legacyValues['time_in']?->toIso8601String(),
                            'time_out' => $legacyValues['time_out']?->toIso8601String(),
                            'am_time_in' => $attendance->am_time_in?->toIso8601String(),
                            'am_time_out' => $attendance->am_time_out?->toIso8601String(),
                            'pm_time_in' => $attendance->pm_time_in?->toIso8601String(),
                            'pm_time_out' => $attendance->pm_time_out?->toIso8601String(),
                        ],
                        'proposed' => $proposed,
                        'reason' => implode(' ', array_unique($reasons)),
                    ];
                }
            }
        });

        $report = [
            'generated_at' => Carbon::now('Asia/Manila')->toIso8601String(),
            'mode' => 'read_only_dry_run',
            'records_inspected' => $recordsInspected,
            'records_requiring_review' => count($records),
            'records' => $records,
        ];
        $path = 'attendance-repair-audit/attendance-repair-audit-'.Carbon::now('Asia/Manila')->format('Ymd-His').'.json';
        $written = Storage::disk('local')->put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        if (! $written) {
            $this->error('Could not write the attendance repair audit report.');

            return Command::FAILURE;
        }

        $this->info('Read-only attendance repair audit completed.');
        $this->line('Records inspected: '.$report['records_inspected']);
        $this->line('Records requiring review: '.$report['records_requiring_review']);
        $this->line('Report: '.Storage::disk('local')->path($path));
        $this->comment('No attendance records were changed.');

        return Command::SUCCESS;
    }

    private function destinationFor(
        string $legacyField,
        Carbon $timestamp,
        Attendance $attendance,
        AttendanceRuleService $ruleService
    ): ?string {
        if ($timestamp->toDateString() !== $attendance->attendance_date->format('Y-m-d')) {
            return null;
        }

        $schedule = $attendance->schedule
            ?? ($attendance->employee
                ? $ruleService->resolveScheduleForEmployeeAndDate($attendance->employee, $attendance->attendance_date)
                : null);
        if (! $schedule) {
            return null;
        }

        $times = $ruleService->getScheduleTimes($schedule, $attendance->attendance_date);

        if ($legacyField === 'time_in') {
            if ($timestamp->lessThan($times['am_time_out'])) {
                return 'am_time_in';
            }
            if (
                $timestamp->greaterThanOrEqualTo($times['pm_time_in'])
                && $timestamp->lessThanOrEqualTo($times['pm_time_out'])
            ) {
                return 'pm_time_in';
            }

            return null;
        }

        if ($timestamp->greaterThan($times['am_time_in']) && $timestamp->lessThanOrEqualTo($times['am_time_out'])) {
            return 'am_time_out';
        }
        if ($timestamp->greaterThanOrEqualTo($times['pm_time_in']) && $timestamp->lessThanOrEqualTo($times['pm_time_out'])) {
            return 'pm_time_out';
        }

        return null;
    }

    private function sessionLabel(string $field): string
    {
        return str_starts_with($field, 'am_') ? 'morning' : 'afternoon';
    }
}
