<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\AttendanceRuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAttendanceUndertimeCommand extends Command
{
    protected $signature = 'attendance:repair-undertime
                            {--from= : First attendance date to inspect (Y-m-d)}
                            {--to= : Last attendance date to inspect (Y-m-d)}
                            {--apply : Save recalculated values; otherwise run a dry run}';

    protected $description = 'Recalculate stored undertime from valid session events without changing timestamps.';

    public function handle(AttendanceRuleService $ruleService): int
    {
        $query = Attendance::query()
            ->with(['employee', 'schedule'])
            ->orderBy('id');

        if ($from = $this->option('from')) {
            $query->whereDate('attendance_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('attendance_date', '<=', $to);
        }

        $apply = (bool) $this->option('apply');
        $candidates = [];
        $skipped = 0;

        $query->chunkById(200, function ($attendances) use ($apply, $ruleService, &$candidates, &$skipped): void {
            foreach ($attendances as $attendance) {
                $schedule = $attendance->schedule
                    ?? ($attendance->employee
                        ? $ruleService->resolveScheduleForEmployeeAndDate(
                            $attendance->employee,
                            $attendance->attendance_date
                        )
                        : null);

                if (! $schedule || $attendance->status === 'on_leave') {
                    $skipped++;

                    continue;
                }

                $proposed = $ruleService->calculateUndertime(
                    $attendance,
                    $schedule,
                    $attendance->attendance_date
                );
                $original = (int) ($attendance->undertime_minutes ?? 0);

                if ($original === $proposed) {
                    continue;
                }

                $candidates[] = [
                    'id' => $attendance->id,
                    'employee_id' => $attendance->employee_id,
                    'date' => $attendance->attendance_date->format('Y-m-d'),
                    'original' => $original,
                    'proposed' => $proposed,
                ];

                if ($apply) {
                    DB::transaction(function () use ($attendance, $ruleService): void {
                        $locked = Attendance::query()
                            ->whereKey($attendance->id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $schedule = $locked->schedule
                            ?? ($locked->employee
                                ? $ruleService->resolveScheduleForEmployeeAndDate(
                                    $locked->employee,
                                    $locked->attendance_date
                                )
                                : null);

                        if (! $schedule || $locked->status === 'on_leave') {
                            return;
                        }

                        $locked->undertime_minutes = $ruleService->calculateUndertime(
                            $locked,
                            $schedule,
                            $locked->attendance_date
                        );
                        $locked->save();
                    });
                }
            }
        });

        $this->info($apply ? 'Undertime repair applied.' : 'Undertime repair dry run; no database changes saved.');
        $this->line('Records with recalculated values: '.count($candidates));
        $this->line('Records skipped (no applicable schedule or on leave): '.$skipped);

        if ($candidates) {
            $this->table(
                ['Attendance ID', 'Employee ID', 'Date', 'Original minutes', 'Proposed minutes'],
                array_map(
                    fn (array $candidate): array => [
                        $candidate['id'],
                        $candidate['employee_id'],
                        $candidate['date'],
                        $candidate['original'],
                        $candidate['proposed'],
                    ],
                    $candidates
                )
            );
        }

        return Command::SUCCESS;
    }
}
