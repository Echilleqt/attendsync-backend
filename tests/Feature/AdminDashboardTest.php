<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_renders_database_attendance_metrics_and_recent_activity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00', 'Asia/Manila'));

        $admin = User::factory()->create(['role' => 'admin']);
        $onTimeEmployee = $this->createEmployee('EMP-ON-TIME');
        $lateEmployee = $this->createEmployee('EMP-LATE');
        $inactiveEmployee = $this->createEmployee('EMP-INACTIVE', 'inactive');

        Attendance::create([
            'employee_id' => $onTimeEmployee->id,
            'attendance_date' => '2026-10-03',
            'time_in' => '2026-10-03 07:55:00',
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $lateEmployee->id,
            'attendance_date' => '2026-10-03',
            'time_in' => '2026-10-03 08:25:00',
            'status' => 'late',
        ]);
        Attendance::create([
            'employee_id' => $inactiveEmployee->id,
            'attendance_date' => '2026-10-03',
            'time_in' => '2026-10-03 08:10:00',
            'status' => 'present',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('presentToday', 1)
            ->assertViewHas('lateToday', 1)
            ->assertViewHas('attendanceTotal', 2)
            ->assertViewHas('attendanceRate', 100)
            ->assertSee('Total Employees')
            ->assertSee('Present Today')
            ->assertSee('Late Today')
            ->assertSee('Attendance Rate')
            ->assertSee('2 of 2 active employees checked in')
            ->assertSee('100')
            ->assertSee('<span class="stat-suffix">%</span>', false)
            ->assertSee('EMP-LATE')
            ->assertSee('EMP-ON-TIME')
            ->assertSee('2026-10-03');
    }

    public function test_employee_cannot_access_the_admin_dashboard(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    private function createEmployee(string $employeeId, string $status = 'active'): Employee
    {
        $user = User::factory()->create(['role' => 'employee']);

        return Employee::create([
            'user_id' => $user->id,
            'employee_id' => $employeeId,
            'first_name' => 'School',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'status' => $status,
        ]);
    }
}
