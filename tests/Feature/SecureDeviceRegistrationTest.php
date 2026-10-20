<?php

namespace Tests\Feature;

use App\Models\ApprovedDevice;
use App\Models\Employee;
use App\Models\FaceBiometricCredential;
use App\Models\FaceVerificationToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecureDeviceRegistrationTest extends TestCase
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
            'email' => 'juan.delacruz@example.com',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-TEST-2026',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'position' => 'Teacher I',
            'department' => 'Science Department',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createFaceToken(Employee $employee, ?string $deviceUuid = null, int $validMinutes = 5): string
    {
        $token = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'device_uuid' => $deviceUuid,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($validMinutes),
        ]);
        return $token;
    }

    public function test_device_status_endpoint_returns_unregistered_when_no_record(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        $response = $this->actingAs($user, 'api')->getJson('/api/devices/status?device_uuid=NEW-DEVICE-999');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registered', false)
            ->assertJsonPath('status', 'unregistered')
            ->assertJsonPath('is_approved', false);
    }

    public function test_device_registration_requires_valid_face_token_and_binds_device(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // 1. Invalid face verification token fails
        $invalidRes = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'NEW-DEVICE-999',
            'device_name' => 'Test Phone',
            'device_model' => 'Pixel 8a',
            'device_os' => 'Android 14',
            'face_verification_token' => Str::random(64),
        ]);
        $invalidRes->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');

        // 2. Token issued for a DIFFERENT device fails
        $diffDeviceToken = $this->createFaceToken($employee, 'OTHER-DEVICE-111');
        $diffRes = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'NEW-DEVICE-999',
            'device_name' => 'Test Phone',
            'device_model' => 'Pixel 8a',
            'device_os' => 'Android 14',
            'face_verification_token' => $diffDeviceToken,
        ]);
        $diffRes->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');

        // 3. Valid token succeeds and is consumed atomically
        $validToken = $this->createFaceToken($employee, 'NEW-DEVICE-999');
        $successRes = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'NEW-DEVICE-999',
            'device_name' => 'Juan Phone',
            'device_model' => 'Pixel 8a',
            'device_os' => 'Android 14',
            'public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...',
            'face_verification_token' => $validToken,
        ]);

        $successRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('device.status', 'pending')
            ->assertJsonPath('device.verified_via_face', true);

        // Verify token cannot be reused
        $reuseRes = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'NEW-DEVICE-999',
            'device_name' => 'Juan Phone',
            'device_model' => 'Pixel 8a',
            'device_os' => 'Android 14',
            'face_verification_token' => $validToken,
        ]);
        $reuseRes->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');
    }

    public function test_existing_face_template_preserved_across_devices(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // Enroll face template
        $templateData = array_map(fn ($i) => 0.54321 + ($i * 0.002), range(1, 128));
        $credential = FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'model_version' => 'dlib_v1',
            'face_template' => $templateData,
            'verified_at' => now(),
        ]);

        $originalHash = md5(json_encode($credential->face_template));

        // Register Device 1
        $token1 = $this->createFaceToken($employee, 'DEVICE-ONE');
        $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'DEVICE-ONE',
            'face_verification_token' => $token1,
        ])->assertCreated();

        // Register Device 2 (e.g. employee changed phones)
        $token2 = $this->createFaceToken($employee, 'DEVICE-TWO');
        $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'DEVICE-TWO',
            'face_verification_token' => $token2,
        ])->assertCreated();

        // Assert face template was preserved and never overwritten or duplicated
        $credential->refresh();
        $this->assertEquals($originalHash, md5(json_encode($credential->face_template)));
        $this->assertEquals(1, FaceBiometricCredential::where('employee_id', $employee->id)->count());
        $this->assertEquals(2, ApprovedDevice::where('employee_id', $employee->id)->count());
    }

    public function test_backend_enforces_device_approval_on_protected_endpoints(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // 1. Unregistered device calling protected attendance endpoint
        $unregRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'UNREGISTERED-DEVICE-001',
        ]);
        $unregRes->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // 2. Pending device calling protected endpoint
        $device = ApprovedDevice::create([
            'employee_id' => $employee->id,
            'device_uuid' => 'PENDING-DEVICE-001',
            'device_model' => 'Galaxy S23',
            'status' => 'pending',
            'verified_via_face' => true,
            'registered_at' => now(),
        ]);

        $pendingRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'PENDING-DEVICE-001',
        ]);
        $pendingRes->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // Correction request blocked while pending
        $pendingCorrection = $this->actingAs($user, 'api')->getJson('/api/attendance-correction-requests', [
            'X-Device-Id' => 'PENDING-DEVICE-001',
        ]);
        $pendingCorrection->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // 3. Admin approves device
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web')
            ->post(route('admin.devices.approve', $device))
            ->assertRedirect();

        $this->assertTrue($device->fresh()->isApproved());

        // 4. Approved device gains access
        $approvedRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'PENDING-DEVICE-001',
        ]);
        $approvedRes->assertOk();

        // 5. Admin rejects device
        $this->actingAs($admin, 'web')
            ->post(route('admin.devices.reject', $device), [
                'rejection_reason' => 'Unrecognized employee device.',
            ])
            ->assertRedirect();

        $this->assertEquals('rejected', $device->fresh()->status);
        $this->assertEquals('Unrecognized employee device.', $device->fresh()->rejection_reason);

        // 6. Rejected device is blocked
        $rejectedRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'PENDING-DEVICE-001',
        ]);
        $rejectedRes->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // 7. Revoked device is blocked
        $this->actingAs($admin, 'web')
            ->post(route('admin.devices.revoke', $device), [
                'reason' => 'Reported lost phone.',
            ])
            ->assertRedirect();

        $this->assertEquals('revoked', $device->fresh()->status);

        $revokedRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'PENDING-DEVICE-001',
        ]);
        $revokedRes->assertUnprocessable()
            ->assertJsonValidationErrors('device');
    }

    /**
     * Requirements 1 & 9: Unauthenticated or inactive users cannot enroll face biometrics
     */
    public function test_unauthenticated_or_inactive_user_cannot_enroll_face(): void
    {
        // 1. Unauthenticated request rejected
        $file = UploadedFile::fake()->image('selfie.jpg', 128, 128);
        $this->postJson('/api/face/enroll', ['face_image' => $file])
            ->assertUnauthorized();

        // 2. Inactive employee rejected
        [$user, $employee] = $this->createEmployeeUser();
        $employee->update(['status' => 'inactive']);

        $response = $this->actingAs($user, 'api')->postJson('/api/face/enroll', [
            'face_image' => $file,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'An active employee profile is required.');

        $this->assertDatabaseMissing('face_biometric_credentials', [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Requirements 1, 2, 3, 5, 6: Direct initial enrollment for newly provisioned active employee
     */
    public function test_new_provisioned_employee_can_enroll_face_directly_and_register_pending_device(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // 1. Check initial face status is not enrolled
        $statusRes = $this->actingAs($user, 'api')->getJson('/api/face/status');
        $statusRes->assertOk()
            ->assertJsonPath('enrolled', false);

        // 2. Active employee directly enrolls face with liveness image
        $mockService = \Mockery::mock(\App\Services\FaceRecognitionService::class);
        $mockService->shouldReceive('validateImage')->once()->andReturnNull();
        $mockService->shouldReceive('generateEmbedding')->once()->andReturn(array_fill(0, 128, 0.123));
        $mockService->shouldReceive('getModelVersion')->andReturn('dlib_face_recognition_v1');
        $this->app->instance(\App\Services\FaceRecognitionService::class, $mockService);

        $file = UploadedFile::fake()->image('enroll.jpg', 128, 128);

        $enrollRes = $this->actingAs($user, 'api')->postJson('/api/face/enroll', [
            'face_image' => $file,
            'device_uuid' => 'NEW-EMPLOYEE-PHONE-001',
        ]);

        $enrollRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('face.enrolled', true)
            ->assertJsonStructure(['face_verification_token']);

        $token = $enrollRes->json('face_verification_token');
        $this->assertNotEmpty($token);

        // Template is bound to employee
        $this->assertDatabaseHas('face_biometric_credentials', [
            'employee_id' => $employee->id,
            'model_version' => 'dlib_face_recognition_v1',
        ]);

        // 3. Submit device registration with the issued token
        $regRes = $this->actingAs($user, 'api')->postJson('/api/devices/register', [
            'device_uuid' => 'NEW-EMPLOYEE-PHONE-001',
            'device_name' => 'New Teacher Phone',
            'device_model' => 'Samsung A54',
            'device_os' => 'Android 14',
            'face_verification_token' => $token,
        ]);

        $regRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('device.status', 'pending')
            ->assertJsonPath('device.verified_via_face', true);

        // 4. Device is pending approval; protected features remain blocked
        $blockedRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'NEW-EMPLOYEE-PHONE-001',
        ]);
        $blockedRes->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        // 5. Admin approves device
        $admin = User::factory()->create(['role' => 'admin']);
        $device = ApprovedDevice::where('device_uuid', 'NEW-EMPLOYEE-PHONE-001')->firstOrFail();
        $this->actingAs($admin, 'web')->post(route('admin.devices.approve', $device));

        $this->assertTrue($device->fresh()->isApproved());

        // 6. Protected features now accessible
        $allowedRes = $this->actingAs($user, 'api')->getJson('/api/attendance/today', [
            'X-Device-Id' => 'NEW-EMPLOYEE-PHONE-001',
        ]);
        $allowedRes->assertOk();
    }

    /**
     * Requirement 4 & 7: Existing employee cannot overwrite template via enrollment
     */
    public function test_existing_employee_cannot_overwrite_face_template_via_enrollment(): void
    {
        [$user, $employee] = $this->createEmployeeUser();

        // Already enrolled
        $originalTemplate = array_fill(0, 128, 0.999);
        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'model_version' => 'dlib_v1',
            'face_template' => $originalTemplate,
            'verified_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('attempt_overwrite.jpg', 128, 128);

        $response = $this->actingAs($user, 'api')->postJson('/api/face/enroll', [
            'face_image' => $file,
        ]);

        // Rejected with 409 Conflict
        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Face biometric template is already enrolled for this employee. Existing employees registering a new device must verify using face verification.');

        // Template intact
        $saved = FaceBiometricCredential::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEquals($originalTemplate, $saved->face_template);
    }
}
