<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceSetting;
use App\Models\BiometricCredential;
use App\Models\BiometricVerification;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_login_and_invalid_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.test',
            'password' => Hash::make('password123'),
            'role' => 'employee',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertUnauthorized();
    }

    public function test_attendance_requires_authentication_photo_and_valid_image(): void
    {
        $this->getJson('/api/attendance/today')->assertUnauthorized();

        [$user] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $this->post('/api/attendance/time-in', [
            'biometric_verification_token' => str_repeat('a', 64),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->post('/api/attendance/time-in', [
            'biometric_verification_token' => str_repeat('a', 64),
            'photo' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_attendance_rejects_a_client_supplied_employee_id(): void
    {
        [$user] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $this->post('/api/attendance/time-in', [
            'employee_id' => 99999,
            'biometric_verification_token' => str_repeat('a', 64),
            'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_attendance_uses_latest_effective_schedule_and_preserves_snapshot(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 08:30:00', 'Asia/Manila'));
        [$user, $employee] = $this->createEmployee();
        AttendanceSetting::create([
            'late_starting_time' => '08:00:00',
            'effective_date' => '2026-10-01',
        ]);
        AttendanceSetting::create([
            'late_starting_time' => '09:00:00',
            'effective_date' => '2026-10-03',
        ]);
        AttendanceSetting::create([
            'late_starting_time' => '07:00:00',
            'effective_date' => '2026-10-04',
        ]);

        $this->actingAs($user, 'api');
        $this->post('/api/attendance/time-in', [
            'biometric_verification_token' => $this->verificationToken($employee),
            'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.late_starting_time_snapshot', '09:00:00');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'status' => 'present',
            'late_starting_time_snapshot' => '09:00:00',
        ]);
    }

    public function test_attendance_at_threshold_is_on_time_and_after_threshold_is_late(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        $this->assertTimeInStatus('08:00:00', 'present');
        $this->assertTimeInStatus('08:00:01', 'late');
    }

    public function test_historical_attendance_status_and_threshold_are_unchanged_by_new_schedule(): void
    {
        [, $employee] = $this->createEmployee();
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-01',
            'time_in' => '2026-10-01 08:15:00',
            'status' => 'late',
            'late_starting_time_snapshot' => '08:00:00',
        ]);

        AttendanceSetting::create([
            'late_starting_time' => '09:00:00',
            'effective_date' => '2026-10-03',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'late',
            'late_starting_time_snapshot' => '08:00:00',
        ]);
    }

    public function test_server_verifies_one_time_signed_biometric_challenges(): void
    {
        [$user] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        $this->assertNotFalse($privateKey);
        openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslConfig());
        $details = openssl_pkey_get_details($privateKey);

        $enrollment = $this->postJson('/api/biometric/credentials', [
            'password' => 'password',
            'verification_method' => 'fingerprint',
            'device_name' => 'Test phone',
            'public_key_pem' => $details['key'],
        ])->assertCreated();

        $challenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $enrollment->json('credential.credential_id'),
            'purpose' => 'biometric-enrollment',
        ])->assertOk();

        openssl_sign(
            $challenge->json('message_to_sign'),
            $signature,
            $privateKeyPem,
            OPENSSL_ALGO_SHA256
        );

        $this->postJson('/api/biometric/verify', [
            'challenge_id' => $challenge->json('challenge_id'),
            'signature' => base64_encode($signature),
        ])->assertOk()->assertJsonPath('credential_verified', true);

        $this->assertNotNull(
            \App\Models\BiometricCredential::where(
                'credential_id',
                $enrollment->json('credential.credential_id')
            )->value('verified_at')
        );

        $attendanceChallenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $enrollment->json('credential.credential_id'),
        ])->assertOk();
        openssl_sign(
            $attendanceChallenge->json('message_to_sign'),
            $attendanceSignature,
            $privateKeyPem,
            OPENSSL_ALGO_SHA256
        );
        $verification = $this->postJson('/api/biometric/verify', [
            'challenge_id' => $attendanceChallenge->json('challenge_id'),
            'signature' => base64_encode($attendanceSignature),
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('biometric_verifications', [
            'employee_id' => $user->employee->id,
            'used_at' => null,
        ]);

        $this->postJson('/api/biometric/verify', [
            'challenge_id' => $challenge->json('challenge_id'),
            'signature' => base64_encode($signature),
        ])->assertUnprocessable();

        $this->assertSame(64, strlen($verification->json('biometric_verification_token')));
    }

    public function test_employee_can_time_out_with_one_registered_fingerprint_biometric(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->createEmployee();
        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        $this->assertNotFalse($privateKey);
        openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslConfig());
        $details = openssl_pkey_get_details($privateKey);
        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) \Illuminate\Support\Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => $details['key'],
            'device_name' => 'Test phone',
            'verified_at' => now(),
        ]);
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today('Asia/Manila')->toDateString(),
            'time_in' => Carbon::today('Asia/Manila')->setTime(8, 0),
            'status' => 'present',
        ]);
        $this->actingAs($user, 'api');

        $challenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $credential->credential_id,
            'purpose' => 'attendance-time-out',
        ])->assertOk();

        openssl_sign(
            $challenge->json('message_to_sign'),
            $signature,
            $privateKeyPem,
            OPENSSL_ALGO_SHA256
        );

        $verification = $this->postJson('/api/biometric/verify', [
            'challenge_id' => $challenge->json('challenge_id'),
            'signature' => base64_encode($signature),
        ])->assertOk();

        $this->post('/api/attendance/time-out', [
            'verification_method' => 'fingerprint',
            'biometric_verification_token' => $verification->json('biometric_verification_token'),
            'photo' => $this->validPngUpload('time-out.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('attendance.id', $attendance->id)
            ->assertJsonPath('attendance.time_out_verification_method', 'fingerprint');
    }

    public function test_challenge_accepts_time_in_and_time_out_aliases_and_issues_valid_tokens(): void
    {
        [$user, $employee] = $this->createEmployee();
        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslConfig());
        $details = openssl_pkey_get_details($privateKey);
        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) \Illuminate\Support\Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => $details['key'],
            'device_name' => 'Alias phone',
            'verified_at' => now(),
        ]);
        $this->actingAs($user, 'api');

        $timeInChallenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $credential->credential_id,
            'purpose' => 'time-in',
        ])->assertOk();

        openssl_sign(
            $timeInChallenge->json('message_to_sign'),
            $timeInSignature,
            $privateKeyPem,
            OPENSSL_ALGO_SHA256
        );

        $timeInVerify = $this->postJson('/api/biometric/verify', [
            'challenge_id' => $timeInChallenge->json('challenge_id'),
            'signature' => base64_encode($timeInSignature),
        ])->assertOk()
            ->assertJsonPath('credential_verified', true);

        $this->assertNotEmpty($timeInVerify->json('biometric_verification_token'));
        $this->assertNotEmpty($timeInVerify->json('verification_token'));

        $timeOutChallenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $credential->credential_id,
            'purpose' => 'time-out',
        ])->assertOk();

        openssl_sign(
            $timeOutChallenge->json('message_to_sign'),
            $timeOutSignature,
            $privateKeyPem,
            OPENSSL_ALGO_SHA256
        );

        $timeOutVerify = $this->postJson('/api/biometric/verify', [
            'challenge_id' => $timeOutChallenge->json('challenge_id'),
            'signature' => base64_encode($timeOutSignature),
        ])->assertOk()
            ->assertJsonPath('credential_verified', true);

        $this->assertNotEmpty($timeOutVerify->json('biometric_verification_token'));
    }

    public function test_attendance_rejects_forged_or_expired_biometric_verification_tokens(): void
    {
        [$user, $employee] = $this->createEmployee();
        $this->actingAs($user, 'api');

        // Forged 64-char hex token
        $this->post('/api/attendance/time-in', [
            'verification_method' => 'fingerprint',
            'biometric_verification_token' => str_repeat('f', 64),
            'photo' => $this->validPngUpload('proof.png'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('biometric_verification_token');

        // Expired token
        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) \Illuminate\Support\Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => 'test-key',
            'device_name' => 'Expired phone',
            'verified_at' => now(),
        ]);
        $expiredToken = bin2hex(random_bytes(32));
        BiometricVerification::create([
            'employee_id' => $employee->id,
            'biometric_credential_id' => $credential->id,
            'verification_method' => 'fingerprint',
            'token_hash' => hash('sha256', $expiredToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post('/api/attendance/time-in', [
            'verification_method' => 'fingerprint',
            'biometric_verification_token' => $expiredToken,
            'photo' => $this->validPngUpload('proof.png'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('biometric_verification_token');
    }

    public function test_face_enrollment_is_rejected_without_a_verification_provider(): void
    {
        [$user] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $this->postJson('/api/biometric/credentials', [
            'password' => 'password',
            'verification_method' => 'face',
            'device_name' => 'Test phone',
            'public_key_pem' => 'not-a-public-key',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('verification_method');

        $this->assertDatabaseCount('biometric_credentials', 0);
    }

    public function test_a_biometric_public_key_cannot_be_reused_for_another_employee_account(): void
    {
        [$firstUser] = $this->createEmployee();
        [$secondUser] = $this->createEmployee();

        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]));
        $this->assertNotFalse($privateKey);
        $details = openssl_pkey_get_details($privateKey);

        $this->actingAs($firstUser, 'api')
            ->postJson('/api/biometric/credentials', [
                'password' => 'password',
                'verification_method' => 'fingerprint',
                'device_name' => 'First phone',
                'public_key_pem' => $details['key'],
            ])
            ->assertCreated();

        $this->actingAs($secondUser, 'api')
            ->postJson('/api/biometric/credentials', [
                'password' => 'password',
                'verification_method' => 'fingerprint',
                'device_name' => 'Second phone',
                'public_key_pem' => $details['key'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('public_key_pem');

        $this->assertDatabaseCount('biometric_credentials', 1);
        $this->assertDatabaseHas('biometric_credentials', [
            'employee_id' => $firstUser->employee->id,
            'public_key_hash' => hash('sha256', $details['key']),
        ]);
    }

    public function test_biometric_enrollment_status_and_revoke_require_backend_confirmation_and_password(): void
    {
        [$user] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $key = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        $details = openssl_pkey_get_details($key);

        $this->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('methods.fingerprint.enrolled', false)
            ->assertJsonPath('methods.face.enrolled', false);

        $enrollment = $this->postJson('/api/biometric/credentials', [
            'password' => 'password',
            'verification_method' => 'fingerprint',
            'device_name' => 'Employee phone',
            'public_key_pem' => $details['key'],
        ])->assertCreated();

        $credentialId = $enrollment->json('credential.credential_id');

        $this->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('methods.fingerprint.enrolled', false)
            ->assertJsonCount(0, 'methods.fingerprint.credentials');

        $challenge = $this->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $credentialId,
            'purpose' => 'biometric-enrollment',
        ])->assertOk();
        openssl_sign(
            $challenge->json('message_to_sign'),
            $signature,
            $key,
            OPENSSL_ALGO_SHA256
        );
        $this->postJson('/api/biometric/verify', [
            'challenge_id' => $challenge->json('challenge_id'),
            'signature' => base64_encode($signature),
        ])->assertOk()->assertJsonPath('credential_verified', true);

        $this->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('methods.fingerprint.enrolled', true)
            ->assertJsonPath('methods.fingerprint.credentials.0.credential_id', $credentialId);

        $this->deleteJson('/api/biometric/credentials', [
            'password' => 'wrong-password',
            'verification_method' => 'fingerprint',
            'credential_id' => $credentialId,
        ])->assertUnprocessable();

        $this->deleteJson('/api/biometric/credentials', [
            'password' => 'password',
            'verification_method' => 'fingerprint',
            'credential_id' => $credentialId,
        ])->assertOk();

        $this->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('methods.fingerprint.enrolled', false);
    }

    public function test_attendance_timestamp_is_returned_from_server_in_manila_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 00:15:00', 'UTC'));
        [$user, $employee] = $this->createEmployee();

        $this->actingAs($user, 'api')
            ->post('/api/attendance/time-in', [
                'biometric_verification_token' => $this->verificationToken($employee),
                'device_time' => '2032-05-01T23:59:59-11:00',
                'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attendance.attendance_date', '2026-10-03')
            ->assertJsonPath('attendance.time_in', '2026-10-03T08:15:00+08:00')
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.photo_url', route('api.attendance.photo', [
                'attendance' => Attendance::firstOrFail()->id,
            ], false));
    }

    public function test_time_out_requires_authentication_and_an_existing_time_in(): void
    {
        $this->post('/api/attendance/time-out', [
            'biometric_verification_token' => str_repeat('a', 64),
            'photo' => $this->validPngUpload('time-out.png'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        [$user, $employee] = $this->createEmployee();
        $this->actingAs($user, 'api');

        $this->post('/api/attendance/time-out', [
            'biometric_verification_token' => $this->verificationToken($employee),
            'photo' => $this->validPngUpload('time-out.png'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');
    }

    public function test_time_out_records_server_time_and_preserves_time_in_photo(): void
    {
        Storage::fake('local');
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-10-07 17:05:00', 'Asia/Manila'));

        [$user, $employee] = $this->createEmployee();
        $timeInPhoto = UploadedFile::fake()
            ->createWithContent('time-in.png', $this->validPngBytes())
            ->store('attendance-proofs', 'local');
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-07',
            'time_in' => '2026-10-07 08:00:00',
            'status' => 'present',
            'photo_path' => $timeInPhoto,
        ]);

        $this->actingAs($user, 'api')
            ->post('/api/attendance/time-out', [
                'biometric_verification_token' => $this->verificationToken($employee),
                'photo' => $this->validPngUpload('time-out.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('attendance.id', $attendance->id)
            ->assertJsonPath('attendance.time_in', '2026-10-07T08:00:00+08:00')
            ->assertJsonPath('attendance.time_out', '2026-10-07T17:05:00+08:00')
            ->assertJsonPath('attendance.photo_url', route('api.attendance.photo', [
                'attendance' => $attendance->id,
            ], false))
            ->assertJsonPath('attendance.time_out_photo_url', route('api.attendance.time-out-photo', [
                'attendance' => $attendance->id,
            ], false));

        $savedAttendance = $attendance->fresh();
        $this->assertSame($timeInPhoto, $savedAttendance->photo_path);
        Storage::disk('local')->assertExists($savedAttendance->photo_path);
        Storage::disk('local')->assertExists($savedAttendance->time_out_photo_path);
    }

    public function test_time_out_cannot_be_recorded_twice(): void
    {
        [$user, $employee] = $this->createEmployee();
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today('Asia/Manila')->toDateString(),
            'time_in' => Carbon::now('Asia/Manila'),
            'time_out' => Carbon::now('Asia/Manila'),
            'status' => 'present',
        ]);

        $this->actingAs($user, 'api')
            ->post('/api/attendance/time-out', [
                'biometric_verification_token' => $this->verificationToken($employee),
                'photo' => $this->validPngUpload('time-out.png'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');
    }

    public function test_only_the_employee_or_an_admin_can_view_an_attendance_photo(): void
    {
        Storage::fake('local');
        [, $employee] = $this->createEmployee();
        $photoPath = UploadedFile::fake()->image('proof.jpg', 64, 64)->store('attendance-proofs', 'local');
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-02',
            'time_in' => '2026-10-02 08:00:00',
            'status' => 'present',
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($employee->user, 'api')
            ->get("/api/attendance/{$attendance->id}/photo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        [$anotherUser] = $this->createEmployee();
        $this->actingAs($anotherUser, 'api')
            ->get("/api/attendance/{$attendance->id}/photo")
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']), 'api')
            ->get("/api/attendance/{$attendance->id}/photo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_time_in_photo_is_removed_if_attendance_database_write_fails(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->createEmployee();
        $this->actingAs($user, 'api')->withoutExceptionHandling();

        Attendance::saving(function (): void {
            throw new RuntimeException('Simulated attendance insert failure.');
        });

        try {
            $this->post('/api/attendance/time-in', [
                'biometric_verification_token' => $this->verificationToken($employee),
                'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
            ], ['Accept' => 'application/json']);
            $this->fail('Expected the simulated database failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated attendance insert failure.', $exception->getMessage());
        }

        Storage::disk('local')->assertDirectoryEmpty('attendance-proofs');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_correction_requests_are_private_and_employees_cannot_approve(): void
    {
        [$employeeUser, $employee] = $this->createEmployee();
        [, $anotherEmployee] = $this->createEmployee();

        $request = AttendanceCorrectionRequest::create([
            'employee_id' => $anotherEmployee->id,
            'attendance_date' => '2026-10-02',
            'request_type' => 'missing_time_in',
            'requested_time_in' => '08:00',
            'reason' => 'Forgot to record my time.',
            'status' => 'pending',
        ]);

        $this->actingAs($employeeUser, 'api');
        $this->getJson("/api/attendance-correction-requests/{$request->id}")
            ->assertNotFound();
        $this->postJson("/api/admin/attendance-correction-requests/{$request->id}/approve")
            ->assertForbidden();

        $response = $this->postJson('/api/attendance-correction-requests', [
            'attendance_date' => '2026-10-02',
            'request_type' => 'missing_time_in',
            'requested_time_in' => '08:00',
            'reason' => 'Forgot to record my time.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('request.employee_id', $employee->id)
            ->assertJsonPath('request.status', 'pending');
    }

    public function test_admin_approval_applies_correction_and_keeps_audit_values(): void
    {
        [, $employee] = $this->createEmployee();
        $admin = User::factory()->create(['role' => 'admin']);
        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-02',
            'request_type' => 'missing_time_in',
            'requested_time_in' => '08:15',
            'reason' => 'Forgot to record my time.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'api')
            ->postJson("/api/admin/attendance-correction-requests/{$correction->id}/approve", [
                'admin_review_notes' => 'Verified against the daily log.',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.original_values.time_in', null)
            ->assertJsonPath('request.approved_values.time_in', '2026-10-02 08:15:00');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-02',
            'time_in' => '2026-10-02 08:15:00',
        ]);
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'reviewed_by' => $admin->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reject_a_correction_request(): void
    {
        [, $employee] = $this->createEmployee();
        $admin = User::factory()->create(['role' => 'admin']);
        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-02',
            'request_type' => 'incorrect_record',
            'requested_time_out' => '16:00',
            'reason' => 'Incorrect time was entered.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'api')
            ->postJson("/api/admin/attendance-correction-requests/{$correction->id}/reject", [
                'admin_review_notes' => 'Insufficient supporting information.',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'rejected');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_admin_web_page_lists_and_applies_attendance_correction_requests(): void
    {
        [, $employee] = $this->createEmployee();
        $admin = User::factory()->create(['role' => 'admin']);
        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-02',
            'request_type' => 'missing_time_in',
            'requested_time_in' => '08:15',
            'reason' => 'Forgot to record my time.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.attendance-correction-requests.index'))
            ->assertOk()
            ->assertSee($employee->employee_id)
            ->assertSee('Evaluate');

        $this->get(route('admin.attendance-correction-requests.show', $correction))
            ->assertOk()
            ->assertSee('Approve and Apply Correction')
            ->assertSee('Reject Request');

        $this->post(route('admin.attendance-correction-requests.approve', $correction), [
            'admin_review_notes' => 'Checked school log.',
        ])->assertRedirect(route('admin.attendance-correction-requests.show', $correction));

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction->id,
            'reviewed_by' => $admin->id,
            'status' => 'approved',
        ]);
    }

    public function test_employee_cannot_open_admin_attendance_requests_page(): void
    {
        [$employeeUser] = $this->createEmployee();

        $this->actingAs($employeeUser)
            ->get(route('admin.attendance-correction-requests.index'))
            ->assertForbidden();
    }

    private function assertTimeInStatus(string $time, string $expectedStatus): void
    {
        Carbon::setTestNow(Carbon::parse("2026-10-03 {$time}", 'Asia/Manila'));
        [$user, $employee] = $this->createEmployee();
        AttendanceSetting::firstOrCreate([
            'effective_date' => '2026-10-01',
        ], [
            'late_starting_time' => '08:00:00',
        ]);

        $this->actingAs($user, 'api')
            ->post('/api/attendance/time-in', [
                'biometric_verification_token' => $this->verificationToken($employee),
                'photo' => UploadedFile::fake()->image('proof.jpg', 64, 64),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attendance.status', $expectedStatus);
    }

    private function createEmployee(): array
    {
        $user = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-' . $user->id,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function verificationToken(Employee $employee): string
    {
        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) \Illuminate\Support\Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => 'test-public-key',
            'device_name' => 'Test device',
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

    private function validPngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->validPngBytes());
    }

    private function validPngBytes(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data))
                .$type
                .$data
                .pack('N', crc32($type.$data));
        };

        $header = pack('N2C5', 64, 64, 8, 6, 0, 0, 0);
        $pixels = gzcompress(str_repeat("\0".str_repeat("\0", 64 * 4), 64));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', $header)
            .$chunk('IDAT', $pixels)
            .$chunk('IEND', '');
    }
}
