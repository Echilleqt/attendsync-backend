<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrudAndIndividualScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@attendsync.test',
        ]);
    }

    private function setupTeachingOrg(): array
    {
        $dept = Department::create([
            'code' => 'TEACH',
            'name' => 'Teaching Department',
            'description' => 'Teaching faculty',
        ]);

        $pos1 = Position::create([
            'department_id' => $dept->id,
            'name' => 'Teacher I',
        ]);

        $pos2 = Position::create([
            'department_id' => $dept->id,
            'name' => 'Teacher II',
        ]);

        $schedule = Schedule::create([
            'name' => 'Grade School',
            'am_time_in' => '07:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
            'late_threshold_am' => '07:30:00',
            'late_threshold_pm' => '13:00:00',
            'is_default' => true,
        ]);

        return [$dept, $pos1, $pos2, $schedule];
    }

    public function test_department_crud_and_safety_checks(): void
    {
        $admin = $this->createAdmin();

        // 1. Create Department
        $res = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'code' => 'ADMIN',
            'name' => 'Administrative Department',
            'description' => 'School administration',
        ]);
        $res->assertRedirect(route('admin.departments.index'));
        $this->assertDatabaseHas('departments', ['code' => 'ADMIN', 'name' => 'Administrative Department']);

        $dept = Department::where('code', 'ADMIN')->firstOrFail();

        // 2. Update Department
        $updateRes = $this->actingAs($admin)->put(route('admin.departments.update', $dept), [
            'code' => 'ADMIN',
            'name' => 'Administrative Services',
            'description' => 'Updated administration',
        ]);
        $updateRes->assertRedirect(route('admin.departments.index'));
        $this->assertEquals('Administrative Services', $dept->fresh()->name);

        // 3. Prevent deletion when position is attached
        $pos = Position::create([
            'department_id' => $dept->id,
            'name' => 'Administrative Officer II',
        ]);

        $deleteAttempt = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));
        $deleteAttempt->assertRedirect(route('admin.departments.index'));
        $deleteAttempt->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $dept->id]);

        // 4. Safely delete when positions are removed
        $pos->delete();
        $safeDelete = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));
        $safeDelete->assertRedirect(route('admin.departments.index'));
        $safeDelete->assertSessionHas('success');
        $this->assertSoftDeleted('departments', ['id' => $dept->id]);
    }

    public function test_position_crud_and_department_compatibility(): void
    {
        $admin = $this->createAdmin();
        [$dept, $pos1] = $this->setupTeachingOrg();

        // 1. Create Position
        $res = $this->actingAs($admin)->post(route('admin.positions.store'), [
            'department_id' => $dept->id,
            'name' => 'Master Teacher I',
            'description' => 'Senior teaching position',
        ]);
        $res->assertRedirect(route('admin.positions.index'));
        $this->assertDatabaseHas('positions', ['name' => 'Master Teacher I', 'department_id' => $dept->id]);

        $newPos = Position::where('name', 'Master Teacher I')->firstOrFail();

        // 2. Update Position
        $this->actingAs($admin)->put(route('admin.positions.update', $newPos), [
            'department_id' => $dept->id,
            'name' => 'Master Teacher II',
            'description' => 'Updated senior position',
        ])->assertRedirect(route('admin.positions.index'));
        $this->assertEquals('Master Teacher II', $newPos->fresh()->name);

        // 3. By Department JSON endpoint
        $byDept = $this->actingAs($admin)->getJson(route('admin.positions.by-department', $dept));
        $byDept->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(2, count($byDept->json('positions')));

        // 4. Safe Delete Position
        $this->actingAs($admin)->delete(route('admin.positions.destroy', $newPos))
            ->assertRedirect(route('admin.positions.index'))
            ->assertSessionHas('success');
        $this->assertSoftDeleted('positions', ['id' => $newPos->id]);
    }

    public function test_work_schedule_crud_and_logical_time_sequence_validation(): void
    {
        $admin = $this->createAdmin();

        // 1. Invalid time sequence: AM Out before AM In
        $invalidRes = $this->actingAs($admin)->post(route('admin.schedules.store'), [
            'name' => 'Invalid Shift 1',
            'am_time_in' => '12:00:00',
            'am_time_out' => '07:30:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
        ]);
        $invalidRes->assertSessionHasErrors('am_time_out');

        // 2. Invalid time sequence: PM In before AM Out
        $invalidRes2 = $this->actingAs($admin)->post(route('admin.schedules.store'), [
            'name' => 'Invalid Shift 2',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:30:00',
            'pm_time_in' => '12:00:00',
            'pm_time_out' => '17:00:00',
        ]);
        $invalidRes2->assertSessionHasErrors('pm_time_in');

        // 3. Valid Schedule Creation
        $validRes = $this->actingAs($admin)->post(route('admin.schedules.store'), [
            'name' => 'Admin Shift C',
            'description' => '8:00 AM - 5:00 PM',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);
        $validRes->assertRedirect(route('admin.schedules.index'));
        $this->assertDatabaseHas('schedules', ['name' => 'Admin Shift C']);

        $schedule = Schedule::where('name', 'Admin Shift C')->firstOrFail();

        // 4. Update Schedule
        $this->actingAs($admin)->put(route('admin.schedules.update', $schedule), [
            'name' => 'Admin Shift C Modified',
            'description' => 'Updated 8:00 AM - 5:00 PM',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ])->assertRedirect(route('admin.schedules.index'));
        $this->assertEquals('Admin Shift C Modified', $schedule->fresh()->name);

        // 5. Delete schedule safely
        $this->actingAs($admin)->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('success');
        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
    }

    public function test_employee_crud_and_individual_schedule_independence(): void
    {
        $admin = $this->createAdmin();
        [$dept, $pos1, $pos2, $sched1] = $this->setupTeachingOrg();

        $sched2 = Schedule::create([
            'name' => 'Kinder',
            'am_time_in' => '07:30:00',
            'am_time_out' => '11:00:00',
            'pm_time_in' => '12:00:00',
            'pm_time_out' => '16:30:00',
            'late_threshold_am' => '07:30:00',
            'late_threshold_pm' => '12:00:00',
            'is_default' => false,
        ]);

        // 1. Create Employee A
        $createA = $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_id' => 'EMP-A',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@attendsync.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_id' => $dept->id,
            'position_id' => $pos1->id,
            'schedule_id' => $sched1->id,
            'date_hired' => '2026-01-15',
            'status' => 'active',
        ]);
        $createA->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('employees', ['employee_id' => 'EMP-A']);

        $empA = Employee::where('employee_id', 'EMP-A')->firstOrFail();

        // 2. Create Employee B
        $createB = $this->actingAs($admin)->post(route('admin.employees.store'), [
            'employee_id' => 'EMP-B',
            'first_name' => 'Maria',
            'last_name' => 'Clara',
            'email' => 'maria@attendsync.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_id' => $dept->id,
            'position_id' => $pos1->id,
            'schedule_id' => $sched1->id,
            'date_hired' => '2026-02-01',
            'status' => 'active',
        ]);
        $createB->assertRedirect(route('admin.employees.index'));
        $empB = Employee::where('employee_id', 'EMP-B')->firstOrFail();

        // Both initially have Schedule 1
        $this->assertEquals($sched1->id, $empA->fresh()->schedule_id);
        $this->assertEquals($sched1->id, $empB->fresh()->schedule_id);

        // 3. Assign Schedule 2 to Employee A ONLY
        $assignRes = $this->actingAs($admin)->post(route('admin.schedules.assign-employee'), [
            'employee_id' => $empA->id,
            'schedule_id' => $sched2->id,
            'effective_date' => '2026-10-01',
            'notes' => 'Shifted to Kinder schedule',
        ]);
        $assignRes->assertRedirect();

        // Verify Employee A has Schedule 2, but Employee B STILL has Schedule 1
        $this->assertEquals($sched2->id, $empA->fresh()->schedule_id);
        $this->assertEquals($sched1->id, $empB->fresh()->schedule_id);

        // 4. Create a custom schedule specifically for Employee B
        $customRes = $this->actingAs($admin)->post(route('admin.schedules.custom-for-employee', $empB), [
            'name' => 'Custom Maria Shift',
            'description' => 'Maria special hours',
            'am_time_in' => '07:45:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:45:00',
            'effective_date' => '2026-10-15',
        ]);
        $customRes->assertRedirect();

        $customSched = Schedule::where('name', 'Custom Maria Shift')->firstOrFail();
        $this->assertEquals($customSched->id, $empB->fresh()->schedule_id);
        // Employee A remains on Sched 2
        $this->assertEquals($sched2->id, $empA->fresh()->schedule_id);

        // 5. Schedule History endpoint
        $historyRes = $this->actingAs($admin)->getJson(route('admin.schedules.employee-history', $empA));
        $historyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('employee.name', 'Juan Dela Cruz');
        $this->assertGreaterThanOrEqual(2, count($historyRes->json('assignments')));

        // 6. Soft-delete employee preserves historical data
        Attendance::create([
            'employee_id' => $empA->id,
            'attendance_date' => '2026-10-10',
            'status' => 'present',
        ]);

        $deleteEmp = $this->actingAs($admin)->delete(route('admin.employees.destroy', $empA));
        $deleteEmp->assertRedirect(route('admin.employees.index'));
        $this->assertSoftDeleted('employees', ['id' => $empA->id]);
        $this->assertDatabaseHas('attendances', ['employee_id' => $empA->id]);
    }
}
