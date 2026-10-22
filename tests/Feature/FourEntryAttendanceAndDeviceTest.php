<?php

namespace Tests\Feature;

use App\Models\ApprovedDevice;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FaceBiometricCredential;
use App\Models\FaceVerificationToken;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DtrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FourEntryAttendanceAndDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createEmployeeUser(): array
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-TEST-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'position' => 'Teacher I',
            'department' => 'Grade 6',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createFaceToken(
        Employee $employee,
        ?string $deviceUuid = null,
        string $purpose = 'attendance',
    ): string
    {
        $token = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'device_uuid' => $deviceUuid,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(5),
        ]);
        return $token;
    }

    private function createDeviceIdentity(): array
    {
        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        $this->assertNotFalse($privateKey);
        openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslConfig());
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $publicKey), true);

        return [
            'device_uuid' => 'AS-' . hash('sha256', $der),
            'private_key' => $privateKeyPem,
            'public_key' => $publicKey,
        ];
    }

    private function deviceProofHeaders(array $identity, string $method, string $uri): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(32));
        $message = implode("\n", [
            'ATTENDSYNC-DEVICE-V1',
            strtoupper($method),
            $uri,
            $timestamp,
            $nonce,
        ]);
        openssl_sign($message, $signature, $identity['private_key'], OPENSSL_ALGO_SHA256);

        return [
            'X-Device-Id' => $identity['device_uuid'],
            'X-Device-Timestamp' => $timestamp,
            'X-Device-Nonce' => $nonce,
            'X-Device-Signature' => base64_encode($signature),
        ];
    }

    /**
     * Test Step 2: Complete four-entry attendance sequence enforced on the server.
     */
    public function test_four_entry_attendance_sequence_and_manila_timestamps(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 07:45:00', 'Asia/Manila'));
        [$user, $employee] = $this->createEmployeeUser();

        // Step 1: Morning Time In (7:45 AM)
        $token1 = $this->createFaceToken($employee);
        $res1 = $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $token1,
            'photo' => UploadedFile::fake()->image('morning_in.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res1->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'morning_in')
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.am_time_in', '2026-10-16T07:45:00+08:00')
            ->assertJsonPath('attendance.next_action', 'morning_out');

        // Cannot record Afternoon In before Morning Out (sequence enforcement)
        $invalidToken = $this->createFaceToken($employee);
        $this->actingAs($user, 'api')->post('/api/attendance/afternoon-in', [
            'face_verification_token' => $invalidToken,
            'photo' => UploadedFile::fake()->image('invalid.jpg', 64, 64),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');

        // Step 2: Morning Time Out (11:55 AM)
        Carbon::setTestNow(Carbon::parse('2026-10-16 11:55:00', 'Asia/Manila'));
        $token2 = $this->createFaceToken($employee);
        $res2 = $this->actingAs($user, 'api')->post('/api/attendance/morning-out', [
            'face_verification_token' => $token2,
            'photo' => UploadedFile::fake()->image('morning_out.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res2->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'morning_out')
            ->assertJsonPath('attendance.am_time_out', '2026-10-16T11:55:00+08:00')
            ->assertJsonPath('attendance.next_action', 'afternoon_in');

        // Step 3: Afternoon Time In (1:05 PM) - target is 1:00 PM, arriving 1:05 PM marks late
        Carbon::setTestNow(Carbon::parse('2026-10-16 13:05:00', 'Asia/Manila'));
        $token3 = $this->createFaceToken($employee);
        $res3 = $this->actingAs($user, 'api')->post('/api/attendance/afternoon-in', [
            'face_verification_token' => $token3,
            'photo' => UploadedFile::fake()->image('afternoon_in.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res3->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'afternoon_in')
            ->assertJsonPath('attendance.status', 'late')
            ->assertJsonPath('attendance.pm_time_in', '2026-10-16T13:05:00+08:00')
            ->assertJsonPath('attendance.next_action', 'afternoon_out');

        // Step 4: Afternoon Time Out (5:00 PM)
        Carbon::setTestNow(Carbon::parse('2026-10-16 17:00:00', 'Asia/Manila'));
        $token4 = $this->createFaceToken($employee);
        $res4 = $this->actingAs($user, 'api')->post('/api/attendance/afternoon-out', [
            'face_verification_token' => $token4,
            'photo' => UploadedFile::fake()->image('afternoon_out.jpg', 64, 64),
        ], ['Accept' => 'application/json']);

        $res4->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'afternoon_out')
            ->assertJsonPath('attendance.pm_time_out', '2026-10-16T17:00:00+08:00')
            ->assertJsonPath('attendance.next_action', 'complete');

        // Verify database state has all four entries
        $saved = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertNotNull($saved->am_time_in);
        $this->assertNotNull($saved->am_time_out);
        $this->assertNotNull($saved->pm_time_in);
        $this->assertNotNull($saved->pm_time_out);
        $this->assertEquals('late', $saved->status);
    }

    /**
     * Test Step 3 & Step 4: Cross-device face template preservation and device registration.
     */
    public function test_cross_device_face_template_preservation_and_device_approval_workflow(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // 1. Enroll face on Device A
        $originalTemplate = array_map(fn ($i) => 0.123456 + ($i * 0.001), range(1, 128));
        $credential = FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'model_version' => 'dlib_v1',
            'face_template' => $originalTemplate,
            'verified_at' => now(),
            'enrollment_metadata' => ['device' => 'Device-A'],
        ]);

        $originalTemplateHash = md5(json_encode($credential->face_template));

        // 2. Employee switches to Device B.
        // Device registration requires face verification using the existing registered template
        $identity = $this->createDeviceIdentity();
        $faceVerificationToken = $this->createFaceToken($employee, $identity['device_uuid'], 'device_registration');

        $regResponse = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => $identity['device_uuid'],
            'device_name' => 'Maria Pixel 8',
            'device_model' => 'Pixel 8',
            'device_os' => 'Android 14',
            'public_key' => $identity['public_key'],
            'face_verification_token' => $faceVerificationToken,
        ], $this->deviceProofHeaders($identity, 'POST', '/api/devices/register'));

        $regResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('device.status', 'pending')
            ->assertJsonPath('device.verified_via_face', true);

        // Verify the original face template was NOT modified or overwritten
        $credential->refresh();
        $this->assertEquals($originalTemplateHash, md5(json_encode($credential->face_template)));
        $this->assertEquals(1, FaceBiometricCredential::where('employee_id', $employee->id)->count());

        // 3. Employee attempts attendance submission on pending Device B -> MUST BE REJECTED
        $attendanceToken = $this->createFaceToken($employee, $identity['device_uuid']);
        $pendingAttempt = $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $attendanceToken,
            'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
        ], [
            'Accept' => 'application/json',
            'X-Device-Id' => $identity['device_uuid'],
        ]);

        $pendingAttempt->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // 4. Admin approves Device B
        $admin = User::factory()->create(['role' => 'admin']);
        $device = ApprovedDevice::where('device_uuid', $identity['device_uuid'])->firstOrFail();

        $this->actingAs($admin, 'web')
            ->post(route('admin.devices.approve', $device))
            ->assertRedirect();

        $this->assertTrue($device->fresh()->isApproved());

        // 5. Employee submits attendance from approved Device B -> MUST SUCCEED
        $validToken = $this->createFaceToken($employee, $identity['device_uuid']);
        $approvedAttempt = $this->actingAs($user, 'api')->post('/api/attendance/morning-in', [
            'face_verification_token' => $validToken,
            'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
        ], [
            'Accept' => 'application/json',
            ...$this->deviceProofHeaders($identity, 'POST', '/api/attendance/morning-in'),
        ]);

        $approvedAttempt->assertCreated()
            ->assertJsonPath('success', true);

        // 6. Admin revokes Device B -> Subsequent attendance submissions blocked
        $this->actingAs($admin, 'web')
            ->post(route('admin.devices.revoke', $device), ['reason' => 'Device lost'])
            ->assertRedirect();

        $this->assertEquals('revoked', $device->fresh()->status);

        $newToken = $this->createFaceToken($employee, $identity['device_uuid']);
        $revokedAttempt = $this->actingAs($user, 'api')->post('/api/attendance/morning-out', [
            'face_verification_token' => $newToken,
            'photo' => UploadedFile::fake()->image('proof2.jpg', 64, 64),
        ], [
            'Accept' => 'application/json',
            'X-Device-Id' => $identity['device_uuid'],
        ]);

        $revokedAttempt->assertUnprocessable()
            ->assertJsonValidationErrors('device');
    }

    /**
     * Test Step 5: Department, Schedule, and CSC Form No. 48 DTR generation.
     */
    public function test_departments_schedules_and_csc_form_48_generation(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // Create department
        $dept = Department::create([
            'code' => 'DEP-MATH',
            'name' => 'Mathematics Department',
            'description' => 'Junior High Math Faculty',
        ]);

        // Create schedule
        $schedule = Schedule::create([
            'name' => 'Standard DepEd Teaching Hours',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'late_threshold_am' => '08:00:00',
            'late_threshold_pm' => '13:00:00',
            'is_default' => true,
        ]);

        $employee->update([
            'department_id' => $dept->id,
            'schedule_id' => $schedule->id,
        ]);

        // Record attendance for day 1 (Oct 1, 2026)
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-01',
            'am_time_in' => Carbon::parse('2026-10-01 07:55:00', 'Asia/Manila'),
            'am_time_out' => Carbon::parse('2026-10-01 12:00:00', 'Asia/Manila'),
            'pm_time_in' => Carbon::parse('2026-10-01 12:58:00', 'Asia/Manila'),
            'pm_time_out' => Carbon::parse('2026-10-01 17:00:00', 'Asia/Manila'),
            'status' => 'present',
        ]);

        // Generate DTR via DtrService
        $dtrService = app(DtrService::class);
        $dtr = $dtrService->generateMonthlyDtr($employee, '2026-10');

        $this->assertEquals('Maria Santos', $dtr['employee']['name']);
        $this->assertEquals('Mathematics Department', $dtr['employee']['department']);
        $this->assertEquals(31, count($dtr['days']));

        // Day 1 entries
        $day1 = $dtr['days'][0];
        $this->assertEquals('7:55', $day1['am_in']);
        $this->assertEquals('12:00', $day1['am_out']);
        $this->assertEquals('12:58', $day1['pm_in']);
        $this->assertEquals('5:00', $day1['pm_out']);
        $this->assertEquals('', $day1['undertime_hours']);

        // Check API endpoint
        $this->actingAs($user, 'api')
            ->getJson('/api/attendance/dtr?month=2026-10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('dtr.employee.name', 'Maria Santos');
    }
}
