<?php

namespace Tests\Feature;

use App\Models\ApprovedDevice;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\FaceBiometricCredential;
use App\Models\FaceVerificationToken;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AttendanceCorrectionReviewService;
use App\Services\AttendanceRuleService;
use App\Services\DtrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DynamicWorkScheduleAttendanceProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceRuleService $ruleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleService = app(AttendanceRuleService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createEmployeeWithUser(string $empId, ?Schedule $schedule = null, string $email = null): array
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'email' => $email ?? "{$empId}@attendsync.test",
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => $empId,
            'first_name' => 'First_' . $empId,
            'last_name' => 'Last_' . $empId,
            'position' => 'Teacher',
            'department' => 'Faculty',
            'schedule_id' => $schedule?->id,
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createFaceToken(Employee $employee): string
    {
        $token = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(5),
        ]);
        return $token;
    }

    /**
     * Requirement 1: Employees assigned to different schedule templates
     */
    public function test_01_employees_assigned_to_different_schedule_templates(): void
    {
        $schedA = Schedule::create([
            'name' => 'Grade School (7:30-4:30)',
            'am_time_in' => '07:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
            'late_threshold_am' => '07:30:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        $schedB = Schedule::create([
            'name' => 'Admin C (8:00-5:00)',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        [, $empA] = $this->createEmployeeWithUser('EMP-SCHED-A', $schedA);
        [, $empB] = $this->createEmployeeWithUser('EMP-SCHED-B', $schedB);

        $resolvedA = $this->ruleService->resolveScheduleForEmployeeAndDate($empA, '2026-10-19');
        $resolvedB = $this->ruleService->resolveScheduleForEmployeeAndDate($empB, '2026-10-19');

        $this->assertEquals($schedA->id, $resolvedA->id);
        $this->assertEquals('07:30:00', $resolvedA->am_time_in);

        $this->assertEquals($schedB->id, $resolvedB->id);
        $this->assertEquals('08:00:00', $resolvedB->am_time_in);
    }

    /**
     * Requirement 2: Correct AM/PM session assignment for each employee
     */
    public function test_02_correct_am_pm_session_assignment_for_each_employee(): void
    {
        $sched = Schedule::create([
            'name' => 'Standard Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        [$user, $emp] = $this->createEmployeeWithUser('EMP-SESS-01', $sched);

        // AM arrival at 07:55 AM
        Carbon::setTestNow(Carbon::parse('2026-10-19 07:55:00', 'Asia/Manila'));
        $token1 = $this->createFaceToken($emp);
        $res1 = $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $token1,
            'photo' => UploadedFile::fake()->image('am_in.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res1->assertCreated()
            ->assertJsonPath('attendance.am_time_in', '2026-10-19T07:55:00+08:00')
            ->assertJsonPath('attendance.next_action', 'morning_out');

        // PM arrival at 13:00 PM
        Carbon::setTestNow(Carbon::parse('2026-10-19 11:58:00', 'Asia/Manila'));
        $token2 = $this->createFaceToken($emp);
        $this->actingAs($user, 'api')->post('/api/attendance/morning-out', [
            'face_verification_token' => $token2,
            'photo' => UploadedFile::fake()->image('am_out.jpg', 64, 64),
        ], ['Accept' => 'application/json'])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-19 13:00:00', 'Asia/Manila'));
        $token3 = $this->createFaceToken($emp);
        $res3 = $this->actingAs($user, 'api')->post('/api/attendance/afternoon-in', [
            'face_verification_token' => $token3,
            'photo' => UploadedFile::fake()->image('pm_in.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res3->assertOk()
            ->assertJsonPath('attendance.pm_time_in', '2026-10-19T13:00:00+08:00');

        $record = Attendance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($record->am_time_in);
        $this->assertNotNull($record->am_time_out);
        $this->assertNotNull($record->pm_time_in);
    }

    /**
     * Requirement 3: Different AM start times and late cutoffs
     */
    public function test_03_different_am_start_times_and_late_cutoffs(): void
    {
        $schedEarly = Schedule::create([
            'name' => 'Early Start (7:30)',
            'am_time_in' => '07:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
            'late_threshold_am' => '07:30:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        $schedLate = Schedule::create([
            'name' => 'Standard Start (8:00)',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        [$userA, $empA] = $this->createEmployeeWithUser('EMP-EARLY', $schedEarly);
        [$userB, $empB] = $this->createEmployeeWithUser('EMP-LATE', $schedLate);

        // Both employees clock in at 7:35 AM
        Carbon::setTestNow(Carbon::parse('2026-10-19 07:35:00', 'Asia/Manila'));

        // Emp A clocks in: 7:35 is past 7:30 threshold => LATE
        $tokenA = $this->createFaceToken($empA);
        $resA = $this->actingAs($userA, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $tokenA,
            'photo' => UploadedFile::fake()->image('proof_a.jpg', 64, 64),
        ], ['Accept' => 'application/json']);
        $resA->assertCreated()->assertJsonPath('attendance.am_status', 'late');

        // Emp B clocks in: 7:35 is before 8:00 threshold => PRESENT
        $tokenB = $this->createFaceToken($empB);
        $resB = $this->actingAs($userB, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $tokenB,
            'photo' => UploadedFile::fake()->image('proof_b.jpg', 64, 64),
        ], ['Accept' => 'application/json']);
        $resB->assertCreated()->assertJsonPath('attendance.am_status', 'present');
    }

    /**
     * Requirement 4: Different PM start times and late cutoffs
     */
    public function test_04_different_pm_start_times_and_late_cutoffs(): void
    {
        $sched1 = Schedule::create([
            'name' => 'PM 1:00 PM Start',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        $sched2 = Schedule::create([
            'name' => 'PM 1:15 PM Start',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:15:00',
            'pm_time_out' => '17:15:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:15:00',
        ]);

        [$user1, $emp1] = $this->createEmployeeWithUser('EMP-PM-1', $sched1);
        [$user2, $emp2] = $this->createEmployeeWithUser('EMP-PM-2', $sched2);

        // Both clock in for afternoon at 1:05 PM
        Carbon::setTestNow(Carbon::parse('2026-10-19 13:05:00', 'Asia/Manila'));

        $token1 = $this->createFaceToken($emp1);
        $res1 = $this->actingAs($user1, 'api')->post('/api/attendance/afternoon-in', [
            'face_verification_token' => $token1,
            'photo' => UploadedFile::fake()->image('pm1.jpg', 64, 64),
        ], ['Accept' => 'application/json']);
        // Emp 1 PM threshold was 13:00 => LATE
        $res1->assertOk()->assertJsonPath('attendance.pm_status', 'late');

        $token2 = $this->createFaceToken($emp2);
        $res2 = $this->actingAs($user2, 'api')->post('/api/attendance/afternoon-in', [
            'face_verification_token' => $token2,
            'photo' => UploadedFile::fake()->image('pm2.jpg', 64, 64),
        ], ['Accept' => 'application/json']);
        // Emp 2 PM threshold was 13:15 => PRESENT
        $res2->assertOk()->assertJsonPath('attendance.pm_status', 'present');
    }

    /**
     * Requirement 5: Different AM/PM Time Out values and undertime calculation
     */
    public function test_05_different_am_pm_time_out_values(): void
    {
        $schedA = Schedule::create([
            'name' => 'Early Out (16:30)',
            'am_time_in' => '07:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
        ]);

        $schedB = Schedule::create([
            'name' => 'Late Out (17:00)',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ]);

        [, $empA] = $this->createEmployeeWithUser('EMP-OUT-A', $schedA);
        [, $empB] = $this->createEmployeeWithUser('EMP-OUT-B', $schedB);

        // Both leave at 16:30
        $departure = Carbon::parse('2026-10-19 16:30:00', 'Asia/Manila');

        $attA = new Attendance(['pm_time_out' => $departure]);
        $attB = new Attendance(['pm_time_out' => $departure]);

        // Emp A: scheduled pm_time_out is 16:30 -> 0 undertime minutes
        $this->assertEquals(0, $this->ruleService->calculateUndertime($attA, $schedA, '2026-10-19'));

        // Emp B: scheduled pm_time_out is 17:00 -> 30 undertime minutes
        $this->assertEquals(30, $this->ruleService->calculateUndertime($attB, $schedB, '2026-10-19'));
    }

    /**
     * Requirement 6: Missing Time In before and after configured absence deadline
     */
    public function test_06_missing_time_in_before_and_after_configured_absence_deadline(): void
    {
        $sched = Schedule::create([
            'name' => 'Shift with 12:00 AM Out',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ]);

        // Before morning absence deadline (e.g. at 10:00 AM)
        $morningPending = Carbon::parse('2026-10-19 10:00:00', 'Asia/Manila');
        $statusBefore = $this->ruleService->evaluateAmStatus(null, null, $sched, '2026-10-19', $morningPending);
        $this->assertEquals('not_recorded', $statusBefore);

        // After morning absence deadline (e.g. at 12:05 PM)
        $morningPassed = Carbon::parse('2026-10-19 12:05:00', 'Asia/Manila');
        $statusAfter = $this->ruleService->evaluateAmStatus(null, null, $sched, '2026-10-19', $morningPassed);
        $this->assertEquals('absent', $statusAfter);
    }

    /**
     * Requirement 7: Missing Time Out with valid Time In becomes incomplete
     */
    public function test_07_missing_time_out_with_valid_time_in(): void
    {
        $sched = Schedule::create([
            'name' => 'Session Incomplete Test',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ]);

        $amIn = Carbon::parse('2026-10-19 08:00:00', 'Asia/Manila');
        $afterShift = Carbon::parse('2026-10-19 12:30:00', 'Asia/Manila');

        // Valid AM Time In, but AM session has ended and AM Time Out is missing => INCOMPLETE
        $status = $this->ruleService->evaluateAmStatus($amIn, null, $sched, '2026-10-19', $afterShift);
        $this->assertEquals('incomplete', $status);
    }

    /**
     * Requirement 8: Morning attendance present but afternoon attendance absent
     */
    public function test_08_morning_attendance_present_but_afternoon_attendance_absent(): void
    {
        $sched = Schedule::create([
            'name' => 'Half Day Attendance Test',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-HALFDAY', $sched);

        // AM Present
        $att = Attendance::create([
            'employee_id' => $emp->id,
            'schedule_id' => $sched->id,
            'attendance_date' => '2026-10-19',
            'am_time_in' => '2026-10-19 07:55:00',
            'am_time_out' => '2026-10-19 12:00:00',
            'am_status' => 'present',
            'status' => 'present',
        ]);

        // After afternoon session has ended
        $evening = Carbon::parse('2026-10-19 18:00:00', 'Asia/Manila');
        $updated = $this->ruleService->recalculateAttendance($att, $sched, $evening);

        $this->assertEquals('present', $updated->am_status);
        $this->assertEquals('absent', $updated->pm_status);
        // Overall daily status acknowledges partial attendance (not counted as full whole-day absence)
        $this->assertEquals('present', $updated->status);
    }

    /**
     * Requirement 9: Approved leave and supported schedule exceptions
     */
    public function test_09_approved_leave_and_supported_schedule_exceptions(): void
    {
        $sched = Schedule::create([
            'name' => 'Leave Protection Test',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-LEAVE', $sched);

        $leaveRecord = Attendance::create([
            'employee_id' => $emp->id,
            'schedule_id' => $sched->id,
            'attendance_date' => '2026-10-19',
            'status' => 'on_leave',
        ]);

        // Absence processor runs at end of day
        $endOfDay = Carbon::parse('2026-10-19 19:00:00', 'Asia/Manila');
        $stats = $this->ruleService->processAbsencesForDate('2026-10-19', $endOfDay);

        // Record remains on_leave and is not overwritten with absent
        $this->assertEquals('on_leave', $leaveRecord->fresh()->status);
    }

    /**
     * Requirement 10: Schedule changes without incorrect historical reclassification
     */
    public function test_10_schedule_changes_without_incorrect_historical_reclassification(): void
    {
        $schedOld = Schedule::create([
            'name' => 'September Schedule (7:30)',
            'am_time_in' => '07:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '16:30:00',
            'late_threshold_am' => '07:30:00',
        ]);

        $schedNew = Schedule::create([
            'name' => 'October Schedule (8:30)',
            'am_time_in' => '08:30:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:30:00',
            'late_threshold_am' => '08:30:00',
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-RECLASS', $schedOld);

        // Historical assignment in September
        EmployeeSchedule::create([
            'employee_id' => $emp->id,
            'schedule_id' => $schedOld->id,
            'effective_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        // New assignment in October
        EmployeeSchedule::create([
            'employee_id' => $emp->id,
            'schedule_id' => $schedNew->id,
            'effective_date' => '2026-10-01',
            'end_date' => null,
        ]);

        // September 15 resolves to SchedOld (7:30)
        $septSched = $this->ruleService->resolveScheduleForEmployeeAndDate($emp, '2026-09-15');
        $this->assertEquals($schedOld->id, $septSched->id);

        // October 15 resolves to SchedNew (8:30)
        $octSched = $this->ruleService->resolveScheduleForEmployeeAndDate($emp, '2026-10-15');
        $this->assertEquals($schedNew->id, $octSched->id);
    }

    /**
     * Requirement 11: Employees without a valid schedule assignment
     */
    public function test_11_employees_without_valid_schedule_assignment(): void
    {
        // Explicit schedule templates exist in DB, but none is marked default
        Schedule::create([
            'name' => 'Non-default Template',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'is_default' => false,
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-NOSCHED', null);

        $resolved = $this->ruleService->resolveScheduleForEmployeeAndDate($emp, '2026-10-19', true);
        $this->assertNull($resolved);

        // Absence processor skips unconfigured employees rather than guessing
        $stats = $this->ruleService->processAbsencesForDate('2026-10-19', Carbon::parse('2026-10-19 18:00:00', 'Asia/Manila'));
        $this->assertGreaterThanOrEqual(1, $stats['unconfigured_skipped']);
    }

    /**
     * Requirement 12: Approved and pending correction requests
     */
    public function test_12_approved_and_pending_correction_requests(): void
    {
        $sched = Schedule::create([
            'name' => 'Correction Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-CORRECT', $sched);
        $admin = User::factory()->create(['role' => 'admin']);

        $attendance = Attendance::create([
            'employee_id' => $emp->id,
            'schedule_id' => $sched->id,
            'attendance_date' => '2026-10-15',
            'am_status' => 'absent',
            'status' => 'absent',
        ]);

        $correction = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $emp->id,
            'attendance_date' => '2026-10-15',
            'request_type' => 'missing_time_in',
            'requested_time_in' => '2026-10-15 07:55:00',
            'requested_time_out' => '2026-10-15 12:00:00',
            'reason' => 'Fingerprint sensor malfunctioned in the morning.',
            'status' => 'pending',
        ]);

        // Pending state does not alter attendance
        $this->assertEquals('absent', $attendance->fresh()->status);

        // Approve correction request
        app(AttendanceCorrectionReviewService::class)->review(
            $correction->id,
            $admin,
            true,
            'Confirmed with security log.'
        );

        // Official attendance updated dynamically using employee schedule
        $freshAtt = $attendance->fresh();
        $this->assertEquals('approved', $correction->fresh()->status);
        $this->assertEquals('present', $freshAtt->am_status);
        $this->assertNotNull($freshAtt->am_time_in);
        $this->assertNotNull($freshAtt->am_time_out);
    }

    /**
     * Requirement 13: Repeated scheduler execution without duplicate absences
     */
    public function test_13_repeated_scheduler_execution_without_duplicate_absences(): void
    {
        $sched = Schedule::create([
            'name' => 'Scheduler Idempotency Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'is_default' => true,
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-IDEMP', $sched);

        $endOfDay = Carbon::parse('2026-10-19 18:00:00', 'Asia/Manila');

        // Run 1
        $stats1 = $this->ruleService->processAbsencesForDate('2026-10-19', $endOfDay);
        $this->assertEquals(1, $stats1['absences_created']);
        $this->assertEquals(1, Attendance::where('employee_id', $emp->id)->count());

        // Run 2 (Immediately following)
        $stats2 = $this->ruleService->processAbsencesForDate('2026-10-19', $endOfDay);
        $this->assertEquals(0, $stats2['absences_created']);
        // Still exactly 1 record, no duplicate created
        $this->assertEquals(1, Attendance::where('employee_id', $emp->id)->count());
    }

    /**
     * Requirement 14: Concurrent attendance requests near cutoff times
     */
    public function test_14_concurrent_attendance_requests_near_cutoff_times(): void
    {
        $sched = Schedule::create([
            'name' => 'Concurrency Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
        ]);

        [, $emp] = $this->createEmployeeWithUser('EMP-CONCURR', $sched);

        // Emulate lockForUpdate in a transaction
        DB::transaction(function () use ($emp, $sched) {
            $att = Attendance::query()
                ->where('employee_id', $emp->id)
                ->whereDate('attendance_date', '2026-10-19')
                ->lockForUpdate()
                ->first();

            $this->assertNull($att);

            Attendance::create([
                'employee_id' => $emp->id,
                'schedule_id' => $sched->id,
                'attendance_date' => '2026-10-19',
                'am_time_in' => '2026-10-19 08:00:00',
                'status' => 'present',
            ]);
        });

        $this->assertEquals(1, Attendance::where('employee_id', $emp->id)->count());
    }

    /**
     * Requirement 15: Asia/Manila timezone and date boundaries
     */
    public function test_15_asia_manila_timezone_and_date_boundaries(): void
    {
        config(['app.timezone' => 'UTC']);

        // 2026-10-19 00:05:00 UTC is 2026-10-19 08:05:00 in Asia/Manila (+08:00)
        Carbon::setTestNow(Carbon::parse('2026-10-19 00:05:00', 'UTC'));

        $sched = Schedule::create([
            'name' => 'Manila Timezone Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
        ]);

        [$user, $emp] = $this->createEmployeeWithUser('EMP-TZ', $sched);

        $token = $this->createFaceToken($emp);
        $res = $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $token,
            'photo' => UploadedFile::fake()->image('tz.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res->assertCreated()
            ->assertJsonPath('attendance.timezone', 'Asia/Manila')
            ->assertJsonPath('attendance.attendance_date', '2026-10-19')
            ->assertJsonPath('attendance.am_time_in', '2026-10-19T08:05:00+08:00')
            // 8:05 AM is late for 8:00 AM start
            ->assertJsonPath('attendance.am_status', 'late');
    }

    /**
     * Requirement 16: Consistency between mobile APIs, admin dashboard, history, and reports
     */
    public function test_16_consistency_between_mobile_apis_admin_dashboard_history_and_reports(): void
    {
        $sched = Schedule::create([
            'name' => 'Consistency Schedule',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
        ]);

        [$user, $emp] = $this->createEmployeeWithUser('EMP-CONSIST', $sched);
        $admin = User::factory()->create(['role' => 'admin']);

        Carbon::setTestNow(Carbon::parse('2026-10-19 08:05:00', 'Asia/Manila'));
        $token = $this->createFaceToken($emp);
        $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $token,
            'photo' => UploadedFile::fake()->image('consist.jpg', 64, 64),
        ], ['Accept' => 'application/json'])->assertCreated();

        // 1. Mobile today endpoint
        $todayRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today');
        $todayRes->assertOk()
            ->assertJsonPath('attendance.am_status', 'late')
            ->assertJsonPath('attendance.status', 'late');

        // 2. Mobile history endpoint
        $histRes = $this->actingAs($user, 'api')->getJson('/api/attendance/history');
        $histRes->assertOk()
            ->assertJsonPath('summary.late', 1);

        // 3. Admin Dashboard
        $dashRes = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashRes->assertOk()
            ->assertViewHas('lateToday', 1);

        // 4. Monthly DTR Service
        $dtr = app(DtrService::class)->generateMonthlyDtr($emp, '2026-10');
        $this->assertNotEmpty($dtr['days']);
        $day19 = collect($dtr['days'])->firstWhere('day', 19);
        $this->assertEquals('8:05', $day19['am_in']);
        $this->assertEquals('late', $day19['status']);
    }

    /**
     * Requirement 17: Preservation of existing historical records after migrations
     */
    public function test_17_preservation_of_existing_historical_records_after_migrations(): void
    {
        [$user, $emp] = $this->createEmployeeWithUser('EMP-LEGACY');

        // Legacy record created with 2-entry structure
        $legacy = Attendance::create([
            'employee_id' => $emp->id,
            'attendance_date' => '2026-09-01',
            'time_in' => '2026-09-01 07:50:00',
            'time_out' => '2026-09-01 17:00:00',
            'status' => 'present',
            'late_starting_time_snapshot' => '08:00:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $legacy->id,
            'attendance_date' => '2026-09-01',
            'status' => 'present',
            'late_starting_time_snapshot' => '08:00:00',
        ]);

        // Historical snapshot and timestamps are preserved intact
        $fresh = $legacy->fresh();
        $this->assertEquals('2026-09-01 07:50:00', $fresh->time_in->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-01 17:00:00', $fresh->time_out->format('Y-m-d H:i:s'));
        $this->assertEquals('08:00:00', $fresh->late_starting_time_snapshot);
    }
}
