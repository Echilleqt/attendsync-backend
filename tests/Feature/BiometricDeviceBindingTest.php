<?php

namespace Tests\Feature;

use App\Models\ApprovedDevice;
use App\Models\BiometricCredential;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BiometricDeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fingerprint_credentials_and_challenges_are_bound_to_the_current_approved_device(): void
    {
        [$user, $employee] = $this->createEmployee();
        $firstDevice = $this->createDevice($employee);
        $secondDevice = $this->createDevice($employee);
        $this->actingAs($user, 'api');

        $firstEnrollment = $this->enrollAndConfirm($firstDevice);
        $firstCredential = $firstEnrollment['credential_id'];
        $this->assertDeviceHasFingerprint($firstDevice, true);
        $this->assertDeviceHasFingerprint($secondDevice, false);

        $firstChallenge = $this->withDeviceProof(
            $firstDevice,
            'POST',
            '/api/biometric/challenges'
        )->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $firstCredential,
        ])->assertOk();
        openssl_sign(
            $firstChallenge->json('message_to_sign'),
            $firstSignature,
            $firstEnrollment['private_key'],
            OPENSSL_ALGO_SHA256
        );
        $this->withDeviceProof($secondDevice, 'POST', '/api/biometric/verify')
            ->postJson('/api/biometric/verify', [
                'challenge_id' => $firstChallenge->json('challenge_id'),
                'signature' => base64_encode($firstSignature),
            ])
            ->assertUnprocessable();

        $this->withDeviceProof($secondDevice, 'POST', '/api/biometric/challenges')
            ->postJson('/api/biometric/challenges', [
                'verification_method' => 'fingerprint',
                'credential_id' => $firstCredential,
                'purpose' => 'attendance-time-in',
            ])
            ->assertNotFound();

        $secondEnrollment = $this->enrollAndConfirm($secondDevice);
        $secondCredential = $secondEnrollment['credential_id'];
        $this->assertNotSame($firstCredential, $secondCredential);
        $this->assertDeviceHasFingerprint($firstDevice, true);
        $this->assertDeviceHasFingerprint($secondDevice, true);

        $attendanceChallenge = $this->withDeviceProof(
            $secondDevice,
            'POST',
            '/api/biometric/challenges'
        )->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $secondCredential,
            'purpose' => 'attendance-time-in',
        ])->assertOk();
        openssl_sign(
            $attendanceChallenge->json('message_to_sign'),
            $signature,
            $secondEnrollment['private_key'],
            OPENSSL_ALGO_SHA256
        );
        $attendanceVerification = $this->withDeviceProof(
            $secondDevice,
            'POST',
            '/api/biometric/verify'
        )->postJson('/api/biometric/verify', [
            'challenge_id' => $attendanceChallenge->json('challenge_id'),
            'signature' => base64_encode($signature),
        ])->assertOk();
        $this->assertNotEmpty(
            $attendanceVerification->json('biometric_verification_token')
        );

        $this->withDeviceProof($firstDevice, 'POST', '/api/attendance/time-in')
            ->post('/api/attendance/time-in', [
                'verification_method' => 'fingerprint',
                'biometric_verification_token' => $attendanceVerification->json(
                    'biometric_verification_token'
                ),
                'photo' => UploadedFile::fake()
                    ->image('cross-device.jpg', 64, 64),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('biometric_verification_token');

        $this->withDeviceProof($secondDevice, 'DELETE', '/api/biometric/credentials')
            ->deleteJson('/api/biometric/credentials', [
                'password' => 'password123',
                'verification_method' => 'fingerprint',
                'credential_id' => $secondCredential,
            ])
            ->assertOk();

        $this->withDeviceProof($secondDevice, 'POST', '/api/biometric/challenges')
            ->postJson('/api/biometric/challenges', [
                'verification_method' => 'fingerprint',
                'credential_id' => $secondCredential,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('biometric_credentials', [
            'credential_id' => $firstCredential,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('biometric_credentials', [
            'credential_id' => $secondCredential,
        ]);
        $this->assertDeviceHasFingerprint($firstDevice, true);
        $this->assertDeviceHasFingerprint($secondDevice, false);
    }

    public function test_pending_and_revoked_devices_cannot_enroll_or_authenticate_fingerprints(): void
    {
        [$user, $employee] = $this->createEmployee();
        $pendingDevice = $this->createDevice($employee, 'pending');
        $approvedDevice = $this->createDevice($employee);
        $this->actingAs($user, 'api');
        $credentialId = $this->enrollAndConfirm($approvedDevice)['credential_id'];

        $newKey = $this->createBiometricKey();
        $this->withDeviceProof($pendingDevice, 'POST', '/api/biometric/credentials')
            ->postJson('/api/biometric/credentials', [
                'password' => 'password123',
                'verification_method' => 'fingerprint',
                'device_name' => 'Unapproved device',
                'public_key_pem' => $newKey['public_key'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        $approvedDevice['device']->update(['status' => 'revoked']);
        $this->withDeviceProof($approvedDevice, 'POST', '/api/biometric/challenges')
            ->postJson('/api/biometric/challenges', [
                'verification_method' => 'fingerprint',
                'credential_id' => $credentialId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device');

        $this->withDeviceProof(
            $approvedDevice,
            'GET',
            '/api/biometric/credentials'
        )->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('current_device_approved', false)
            ->assertJsonPath('methods.fingerprint.enrolled', false)
            ->assertJsonCount(0, 'methods.fingerprint.credentials');
    }

    public function test_legacy_employee_scoped_fingerprint_is_not_reported_as_enrolled(): void
    {
        [$user, $employee] = $this->createEmployee();
        $device = $this->createDevice($employee);
        BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) Str::uuid(),
            'verification_method' => 'fingerprint',
            'public_key_pem' => $this->createBiometricKey()['public_key'],
            'device_name' => 'Unbound legacy credential',
            'verified_at' => now(),
        ]);
        $this->actingAs($user, 'api');

        $this->assertDeviceHasFingerprint($device, false);
    }

    private function enrollAndConfirm(array $device): array
    {
        $key = $this->createBiometricKey();
        $enrollment = $this->withDeviceProof(
            $device,
            'POST',
            '/api/biometric/credentials'
        )->postJson('/api/biometric/credentials', [
            'password' => 'password123',
            'verification_method' => 'fingerprint',
            'device_name' => 'Approved test phone',
            'public_key_pem' => $key['public_key'],
        ])->assertCreated();

        $credentialId = $enrollment->json('credential.credential_id');
        $challenge = $this->withDeviceProof(
            $device,
            'POST',
            '/api/biometric/challenges'
        )->postJson('/api/biometric/challenges', [
            'verification_method' => 'fingerprint',
            'credential_id' => $credentialId,
            'purpose' => 'biometric-enrollment',
        ])->assertOk();

        openssl_sign(
            $challenge->json('message_to_sign'),
            $signature,
            $key['private_key'],
            OPENSSL_ALGO_SHA256
        );

        $this->withDeviceProof($device, 'POST', '/api/biometric/verify')
            ->postJson('/api/biometric/verify', [
                'challenge_id' => $challenge->json('challenge_id'),
                'signature' => base64_encode($signature),
            ])
            ->assertOk()
            ->assertJsonPath('credential_verified', true);

        return [
            'credential_id' => $credentialId,
            'private_key' => $key['private_key'],
        ];
    }

    private function assertDeviceHasFingerprint(array $device, bool $enrolled): void
    {
        $response = $this->withDeviceProof(
            $device,
            'GET',
            '/api/biometric/credentials'
        )->getJson('/api/biometric/credentials')
            ->assertOk()
            ->assertJsonPath('current_device_approved', true)
            ->assertJsonPath('methods.fingerprint.enrolled', $enrolled);

        $response->assertJsonCount(
            $enrolled ? 1 : 0,
            'methods.fingerprint.credentials'
        );
    }

    private function createEmployee(): array
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'password' => Hash::make('password123'),
        ]);
        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-'.$user->id,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createDevice(Employee $employee, string $status = 'approved'): array
    {
        $identity = $this->createBiometricKey();
        $publicDer = base64_decode(
            preg_replace('/-----[^-]+-----|\s+/', '', $identity['public_key']),
            true
        );
        $deviceUuid = 'AS-'.hash('sha256', $publicDer);
        $device = ApprovedDevice::create([
            'employee_id' => $employee->id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Test Android device',
            'device_os' => 'Android',
            'public_key' => $identity['public_key'],
            'status' => $status,
            'registered_at' => now(),
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        return [
            'device' => $device,
            'device_uuid' => $deviceUuid,
            'private_key' => $identity['private_key'],
            'public_key' => $identity['public_key'],
        ];
    }

    private function createBiometricKey(): array
    {
        $privateKey = openssl_pkey_new($this->opensslConfig([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]));
        $this->assertNotFalse($privateKey);
        openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslConfig());
        $details = openssl_pkey_get_details($privateKey);

        return [
            'private_key' => $privateKeyPem,
            'public_key' => $details['key'],
        ];
    }

    private function withDeviceProof(array $device, string $method, string $uri): self
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
        openssl_sign(
            $message,
            $signature,
            $device['private_key'],
            OPENSSL_ALGO_SHA256
        );

        return $this->withHeaders([
            'X-Device-Id' => $device['device_uuid'],
            'X-Device-Timestamp' => $timestamp,
            'X-Device-Nonce' => $nonce,
            'X-Device-Signature' => base64_encode($signature),
        ]);
    }
}
