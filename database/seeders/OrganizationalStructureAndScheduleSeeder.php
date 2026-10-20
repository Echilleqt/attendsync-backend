<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class OrganizationalStructureAndScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Departments
        $teaching = Department::firstOrCreate(
            ['code' => 'TEACH'],
            [
                'name' => 'Teaching Department',
                'description' => 'Responsible for delivering quality education, guiding student learning, and promoting academic excellence.',
            ]
        );

        $admin = Department::firstOrCreate(
            ['code' => 'ADMIN'],
            [
                'name' => 'Administrative Department',
                'description' => 'Responsible for managing school operations, maintaining records, and ensuring efficient administrative services.',
            ]
        );

        // 2. Teaching Positions
        $teachingPositions = [
            'Teacher I',
            'Teacher II',
            'Teacher III',
            'Teacher IV',
            'Teacher V',
            'Teacher VI',
        ];

        foreach ($teachingPositions as $posName) {
            Position::firstOrCreate(
                [
                    'department_id' => $teaching->id,
                    'name' => $posName,
                ],
                [
                    'description' => "Teaching faculty position: {$posName}",
                ]
            );
        }

        // 3. Administrative Positions
        $adminPositions = [
            'Principal III',
            'Administrative Officer II',
            'Project Development Officer I',
            'School Counselor Associate I',
            'Administrative Assistant III',
            'Administrative Assistant II',
            'Security Officer',
            'Maintenance Officer',
        ];

        foreach ($adminPositions as $posName) {
            Position::firstOrCreate(
                [
                    'department_id' => $admin->id,
                    'name' => $posName,
                ],
                [
                    'description' => "Administrative staff position: {$posName}",
                ]
            );
        }

        // Normalize legacy spelling in employee records
        Employee::where('position', 'School Councelor Associate I')
            ->update(['position' => 'School Counselor Associate I']);

        // Link existing employees to departments & positions if match
        foreach (Employee::all() as $emp) {
            $updates = [];
            if (!$emp->department_id) {
                if (stripos($emp->department ?? '', 'teach') !== false) {
                    $updates['department_id'] = $teaching->id;
                    $updates['department'] = 'Teaching Department';
                } elseif (stripos($emp->department ?? '', 'admin') !== false) {
                    $updates['department_id'] = $admin->id;
                    $updates['department'] = 'Administrative Department';
                }
            }

            $deptId = $updates['department_id'] ?? $emp->department_id;
            if ($deptId && !$emp->position_id) {
                $matchedPos = Position::where('department_id', $deptId)
                    ->where('name', 'LIKE', '%' . ($emp->position ?? '') . '%')
                    ->first();
                if ($matchedPos) {
                    $updates['position_id'] = $matchedPos->id;
                }
            }

            if (!empty($updates)) {
                $emp->update($updates);
            }
        }

        // 4. Initial Work Schedule Templates
        $schedules = [
            [
                'name' => 'Grade School',
                'description' => 'Regular grade school faculty schedule (7:30 AM–12:00 PM, 1:00 PM–4:30 PM)',
                'am_time_in' => '07:30:00',
                'am_time_out' => '12:00:00',
                'pm_time_in' => '13:00:00',
                'pm_time_out' => '16:30:00',
                'late_threshold_am' => '07:30:00',
                'late_threshold_pm' => '13:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => true,
            ],
            [
                'name' => 'Kinder',
                'description' => 'Kindergarten teacher shift (7:30 AM–11:00 AM, 12:00 PM–4:30 PM)',
                'am_time_in' => '07:30:00',
                'am_time_out' => '11:00:00',
                'pm_time_in' => '12:00:00',
                'pm_time_out' => '16:30:00',
                'late_threshold_am' => '07:30:00',
                'late_threshold_pm' => '12:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => false,
            ],
            [
                'name' => 'Admin A',
                'description' => 'Administrative schedule Option A (7:30 AM–12:00 PM, 1:00 PM–4:30 PM)',
                'am_time_in' => '07:30:00',
                'am_time_out' => '12:00:00',
                'pm_time_in' => '13:00:00',
                'pm_time_out' => '16:30:00',
                'late_threshold_am' => '07:30:00',
                'late_threshold_pm' => '13:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => false,
            ],
            [
                'name' => 'Admin B',
                'description' => 'Administrative schedule Option B (7:45 AM–12:00 PM, 1:00 PM–4:45 PM)',
                'am_time_in' => '07:45:00',
                'am_time_out' => '12:00:00',
                'pm_time_in' => '13:00:00',
                'pm_time_out' => '16:45:00',
                'late_threshold_am' => '07:45:00',
                'late_threshold_pm' => '13:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => false,
            ],
            [
                'name' => 'Admin C',
                'description' => 'Administrative schedule Option C (8:00 AM–12:00 PM, 1:00 PM–5:00 PM)',
                'am_time_in' => '08:00:00',
                'am_time_out' => '12:00:00',
                'pm_time_in' => '13:00:00',
                'pm_time_out' => '17:00:00',
                'late_threshold_am' => '08:00:00',
                'late_threshold_pm' => '13:00:00',
                'grace_period_minutes' => 0,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_default' => false,
            ],
        ];

        foreach ($schedules as $schedData) {
            Schedule::updateOrCreate(
                ['name' => $schedData['name']],
                $schedData
            );
        }
    }
}
