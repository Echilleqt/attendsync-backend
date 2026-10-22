<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Services\AttendanceRuleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAttendanceStatusesCommand extends Command
{
    protected $signature = 'attendance:repair-statuses
                            {--id= : Attendance database ID to inspect}
                            {--from= : First attendance date to inspect (Y-m-d)}
                            {--to= : Last attendance date to inspect (Y-m-d)}
                            {--apply : Save recalculated statuses; otherwise run a dry run}';

    protected $description = 'Recalculate derived attendance statuses without changing recorded events.';

    public function handle(AttendanceRuleService $ruleService): int
    {
        if (! $this->option('id') && ! $this->option('from') && ! $this->option('to')) {
            $this->error('Specify --id, --from, or --to to limit the records being inspected.');

            return Command::FAILURE;
        }

        $query = Attendance::query()
            ->with(['employee', 'schedule'])
            ->orderBy('id');

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }
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

                $proposed = $this->recalculated($attendance, $schedule, $ruleService);
                if (! $this->hasDerivedChanges($attendance, $proposed)) {
                    continue;
                }

                $candidates[] = [
                    'id' => $attendance->id,
                    'date' => $attendance->attendance_date->format('Y-m-d'),
                    'old_status' => $attendance->status,
                    'new_status' => $proposed->status,
                    'old_am_status' => $attendance->am_status,
                    'new_am_status' => $proposed->am_status,
                    'old_pm_status' => $attendance->pm_status,
                    'new_pm_status' => $proposed->pm_status,
                    'old_undertime' => $attendance->undertime_minutes ?? 0,
                    'new_undertime' => $proposed->undertime_minutes ?? 0,
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

                        $ruleService->recalculateAttendance(
                            $locked,
                            $schedule,
                            $this->evaluationTime($locked)
                        );
                        $locked->save();
                    });
                }
            }
        });

        $this->info($apply ? 'Attendance status repair applied.' : 'Attendance status repair dry run; no database changes saved.');
        $this->line('Records with recalculated values: '.count($candidates));
        $this->line('Records skipped (no applicable schedule or on leave): '.$skipped);

        if ($candidates) {
            $this->table(
                ['ID', 'Date', 'Status', 'AM', 'PM', 'Undertime'],
                array_map(
                    fn (array $candidate): array => [
                        $candidate['id'],
                        $candidate['date'],
                        $candidate['old_status'].' -> '.$candidate['new_status'],
                        $candidate['old_am_status'].' -> '.$candidate['new_am_status'],
                        $candidate['old_pm_status'].' -> '.$candidate['new_pm_status'],
                        $candidate['old_undertime'].' -> '.$candidate['new_undertime'],
                    ],
                    $candidates
                )
            );
        }

        return Command::SUCCESS;
    }

    private function recalculated(Attendance $attendance, Schedule $schedule, AttendanceRuleService $ruleService): Attendance
    {
        $copy = clone $attendance;

        return $ruleService->recalculateAttendance(
            $copy,
            $schedule,
            $this->evaluationTime($attendance)
        );
    }

    private function evaluationTime(Attendance $attendance): Carbon
    {
        $date = $attendance->attendance_date->copy()->timezone('Asia/Manila');
        $today = Carbon::today('Asia/Manila');

        return $date->lessThan($today) ? $date->endOfDay() : Carbon::now('Asia/Manila');
    }

    private function hasDerivedChanges(Attendance $original, Attendance $proposed): bool
    {
        return $original->status !== $proposed->status
            || $original->am_status !== $proposed->am_status
            || $original->pm_status !== $proposed->pm_status
            || (int) ($original->undertime_minutes ?? 0) !== (int) ($proposed->undertime_minutes ?? 0)
            || (int) $original->schedule_id !== (int) $proposed->schedule_id;
    }
}
