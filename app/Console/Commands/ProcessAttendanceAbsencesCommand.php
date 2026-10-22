<?php

namespace App\Console\Commands;

use App\Services\AttendanceRuleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessAttendanceAbsencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:process-absences
                            {--date= : The attendance date to process in Y-m-d format (defaults to today in Asia/Manila)}
                            {--dry-run : Run without persisting changes to the database}';

    /**
     * The console command description.
     */
    protected $description = 'Process employee absences and incomplete sessions dynamically based on individual assigned schedules.';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceRuleService $ruleService): int
    {
        $dateInput = $this->option('date');
        $date = $dateInput
            ? Carbon::parse($dateInput, 'Asia/Manila')->toDateString()
            : Carbon::today('Asia/Manila')->toDateString();

        $now = Carbon::now('Asia/Manila');

        $dryRun = (bool) $this->option('dry-run');
        $this->info("ATTENDSYNC: Processing dynamic schedule attendance & absences for date: {$date}".($dryRun ? ' (dry run)' : ''));
        $this->comment("Current server time (Asia/Manila): {$now->format('Y-m-d H:i:s T')}");

        $stats = $ruleService->processAbsencesForDate($date, $now, $dryRun);

        $absenceMetric = $dryRun ? 'Absence Records That Would Be Created' : 'Absence Records Created';
        $updateMetric = $dryRun ? 'Attendance Records That Would Be Updated' : 'Attendance Records Updated';
        $this->table(
            ['Metric', 'Count / Details'],
            [
                ['Target Date', $stats['date']],
                ['Evaluated At', $stats['evaluated_at']],
                ['Total Active Employees', $stats['total_active_employees']],
                [$absenceMetric, $dryRun ? $stats['absences_would_be_created'] : $stats['absences_created']],
                [$updateMetric, $dryRun ? $stats['records_would_be_updated'] : $stats['records_updated']],
                ['Rest Days / Weekends Skipped', $stats['rest_days_skipped']],
                ['Unconfigured Schedules Skipped', $stats['unconfigured_skipped']],
            ]
        );

        if (!empty($stats['unconfigured_employees'])) {
            $this->warn("The following employees lack an assigned work schedule or default schedule:");
            foreach ($stats['unconfigured_employees'] as $emp) {
                $this->line(" - #{$emp['id']} {$emp['name']} ({$emp['employee_id']})");
            }
        }

        $this->info($dryRun
            ? 'Attendance absence dry run completed; no database changes were saved.'
            : 'Attendance absence processing completed successfully.');

        return Command::SUCCESS;
    }
}
