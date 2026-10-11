<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BiometricCredential;
use App\Models\BiometricVerification;
use App\Models\Employee;
use App\Models\FaceBiometricCredential;
use App\Models\FaceVerificationToken;
use App\Models\User;
use App\Services\FaceRecognitionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FaceRecognitionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createEmployeeUser(string $empId = 'E-101'): array
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'password' => bcrypt('password123'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => $empId,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'position' => 'Teacher',
            'department' => 'High School',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function validPngUpload(string $name = 'face.png'): UploadedFile
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data))
                .$type
                .$data
                .pack('N', crc32($type.$data));
        };

        $header = pack('N2C5', 64, 64, 8, 6, 0, 0, 0);
        $pixels = gzcompress(str_repeat("\0".str_repeat("\0", 64 * 4), 64));

        $content = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', $header)
            .$chunk('IDAT', $pixels)
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function biometricVerificationToken(Employee $employee): string
    {
        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => 'test-public-key',
            'device_name' => 'Test Mobile Device',
        ]);
        $token = bin2hex(random_bytes(32));

        BiometricVerification::create([
            'employee_id' => $employee->id,
            'biometric_credential_id' => $credential->id,
            'verification_method' => 'fingerprint',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $token;
    }

    public function test_face_status_returns_not_enrolled_for_an_active_employee(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-101');

        $this->actingAs($user, 'api')
            ->getJson('/api/face/status')
            ->assertOk()
            ->assertJsonPath('enrolled', false)
            ->assertJsonPath('face', null);
    }

    public function test_face_status_returns_enrolled_when_credential_exists(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-102');

        $credential = FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => [0.1, 0.2, 0.3],
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/face/status')
            ->assertOk()
            ->assertJsonPath('enrolled', true)
            ->assertJsonPath('face.model_version', 'dlib_face_recognition_v1');

        $this->assertNotNull($credential->face_template);
    }

    public function test_face_enrollment_requires_an_image(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-103');

        $this->actingAs($user, 'api')
            ->postJson('/api/face/enroll', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('face_image');
    }

    public function test_face_enrollment_succeeds_and_stores_embedding(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-104');

        $mockService = Mockery::mock(FaceRecognitionService::class);
        $mockService->shouldReceive('validateImage')->once()->andReturnNull();
        $mockService->shouldReceive('generateEmbedding')->once()->andReturn(array_fill(0, 128, 0.05));
        $mockService->shouldReceive('getModelVersion')->andReturn('dlib_face_recognition_v1');
        $this->app->instance(FaceRecognitionService::class, $mockService);

        $file = $this->validPngUpload('selfie.png');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/face/enroll', [
                'face_image' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonPath('face.enrolled', true)
            ->assertJsonPath('face.model_version', 'dlib_face_recognition_v1')
            ->assertJsonPath('message', 'Face recognition enrolled successfully.');

        $this->assertDatabaseHas('face_biometric_credentials', [
            'employee_id' => $employee->id,
            'model_version' => 'dlib_face_recognition_v1',
        ]);

        $savedCredential = FaceBiometricCredential::where('employee_id', $employee->id)->firstOrFail();
        $this->assertCount(128, $savedCredential->face_template);
    }

    public function test_face_verification_requires_a_biometric_enrollment(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-105');

        $this->actingAs($user, 'api')
            ->postJson('/api/face/verify', [
                'face_image' => $this->validPngUpload(),
            ])
            ->assertNotFound()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('message', 'Face recognition has not been enrolled for this employee.');
    }

    public function test_face_verification_requires_an_image(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-106');

        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => array_fill(0, 128, 0.01),
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/face/verify', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('face_image');
    }

    public function test_face_verification_succeeds_and_issues_token_on_match(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-107');

        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => array_fill(0, 128, 0.01),
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $mockService = Mockery::mock(FaceRecognitionService::class);
        $mockService->shouldReceive('validateImage')->once()->andReturnNull();
        $mockService->shouldReceive('generateEmbedding')->once()->andReturn(array_fill(0, 128, 0.012));
        $mockService->shouldReceive('verificationResult')->once()->andReturn([
            'distance' => 0.02,
            'confidence' => 0.98,
            'verified' => true,
            'threshold' => 0.60,
        ]);
        $this->app->instance(FaceRecognitionService::class, $mockService);

        $file = $this->validPngUpload('verify.png');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/face/verify', [
                'face_image' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('message', 'Face verification successful.')
            ->assertJsonPath('confidence', 0.98);

        $token = $response->json('face_verification_token');
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));

        $this->assertDatabaseHas('face_verification_tokens', [
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'used_at' => null,
        ]);
    }

    public function test_face_verification_fails_when_face_does_not_match(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-108');

        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => array_fill(0, 128, 0.01),
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $mockService = Mockery::mock(FaceRecognitionService::class);
        $mockService->shouldReceive('validateImage')->once()->andReturnNull();
        $mockService->shouldReceive('generateEmbedding')->once()->andReturn(array_fill(0, 128, 0.85));
        $mockService->shouldReceive('verificationResult')->once()->andReturn([
            'distance' => 0.84,
            'confidence' => 0.16,
            'verified' => false,
            'threshold' => 0.60,
        ]);
        $this->app->instance(FaceRecognitionService::class, $mockService);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/face/verify', [
                'face_image' => $this->validPngUpload('stranger.png'),
            ]);

        $response->assertOk()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('message', 'Face verification failed.')
            ->assertJsonPath('confidence', 0.16);

        $this->assertDatabaseCount('face_verification_tokens', 0);
    }

    public function test_face_verification_token_is_consumed_by_attendance_time_in(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-109');

        $faceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $faceToken),
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'verification_method' => 'face',
                'face_verification_token' => $faceToken,
                'photo' => $this->validPngUpload('proof.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('face_verification_tokens', [
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $faceToken),
        ]);

        $tokenRecord = FaceVerificationToken::where('token_hash', hash('sha256', $faceToken))->firstOrFail();
        $this->assertNotNull($tokenRecord->used_at);
    }

    public function test_face_verification_token_cannot_be_reused(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-110');

        $faceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $faceToken),
            'expires_at' => now()->addMinutes(2),
            'used_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'verification_method' => 'face',
                'face_verification_token' => $faceToken,
                'photo' => $this->validPngUpload('proof.png'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');
    }

    public function test_face_verification_token_fails_when_expired(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-111');

        $faceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $faceToken),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'verification_method' => 'face',
                'face_verification_token' => $faceToken,
                'photo' => $this->validPngUpload('proof.png'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');
    }

    public function test_attendance_requires_face_token_when_config_enabled(): void
    {
        config(['face-recognition.require_for_attendance' => true]);

        [$user, $employee] = $this->createEmployeeUser('E-112');
        $biometricToken = $this->biometricVerificationToken($employee);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'biometric_verification_token' => $biometricToken,
                'photo' => $this->validPngUpload('proof.png'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_verification_token');
    }

    public function test_face_revoke_succeeds_with_correct_password(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-113');

        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => [0.1, 0.2, 0.3],
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/face/revoke', [
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Face biometric credential removed successfully.');

        $this->assertDatabaseMissing('face_biometric_credentials', [
            'employee_id' => $employee->id,
        ]);

        // Status should now be not enrolled
        $this->actingAs($user, 'api')
            ->getJson('/api/face/status')
            ->assertOk()
            ->assertJsonPath('enrolled', false);
    }

    public function test_face_revoke_fails_with_incorrect_password(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-114');

        FaceBiometricCredential::create([
            'employee_id' => $employee->id,
            'face_template' => [0.1, 0.2, 0.3],
            'model_version' => 'dlib_face_recognition_v1',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/face/revoke', [
                'password' => 'incorrect-pass',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('face_biometric_credentials', [
            'employee_id' => $employee->id,
        ]);
    }

    public function test_face_revoke_fails_when_not_enrolled(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-115');

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/face/revoke', [
                'password' => 'password123',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No face biometric credential is currently enrolled.');
    }

    public function test_employee_can_record_time_in_using_only_face_recognition(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-116');

        $faceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $faceToken),
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'verification_method' => 'face',
                'face_verification_token' => $faceToken,
                'photo' => $this->validPngUpload('selfie_proof.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance.time_in_verification_method', 'face');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'time_in_verification_method' => 'face',
        ]);
    }

    public function test_employee_can_record_time_out_using_only_face_recognition(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-117');

        // Record time in first using face
        $timeInFaceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $timeInFaceToken),
            'expires_at' => now()->addMinutes(2),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'face_verification_token' => $timeInFaceToken,
                'photo' => $this->validPngUpload('time_in.png'),
            ])->assertCreated();

        // Record time out with fresh face token
        $timeOutFaceToken = Str::random(64);
        FaceVerificationToken::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $timeOutFaceToken),
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-out', [
                'verification_method' => 'face',
                'face_verification_token' => $timeOutFaceToken,
                'photo' => $this->validPngUpload('time_out.png'),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance.time_out_verification_method', 'face');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'time_out_verification_method' => 'face',
        ]);
    }

    public function test_employee_can_record_time_in_using_only_fingerprint(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-118');

        $biometricToken = $this->biometricVerificationToken($employee);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'verification_method' => 'fingerprint',
                'biometric_verification_token' => $biometricToken,
                'photo' => $this->validPngUpload('fingerprint_proof.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance.time_in_verification_method', 'fingerprint');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'time_in_verification_method' => 'fingerprint',
        ]);
    }

    public function test_attendance_fails_when_neither_biometric_token_is_provided(): void
    {
        [$user, $employee] = $this->createEmployeeUser('E-119');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/attendance/time-in', [
                'photo' => $this->validPngUpload('proof.png'),
            ]);

        $response->assertUnprocessable();
    }
}
