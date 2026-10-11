<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricChallenge;
use App\Models\BiometricCredential;
use App\Models\BiometricVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BiometricVerificationController extends Controller
{
    public function enroll(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user->employee;

        if (!$employee || $user->role !== 'employee' || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee account is required.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'verification_method' => [
                'required',
                'in:fingerprint',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'face') {
                        $fail('Face recognition enrollment requires a configured face identity and liveness verification provider.');
                    }
                },
            ],
            'device_name' => ['required', 'string', 'max:100'],
            'public_key_pem' => ['required', 'string', 'max:8192'],
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $publicKey = openssl_pkey_get_public($validated['public_key_pem']);
        $keyDetails = $publicKey
            ? openssl_pkey_get_details($publicKey)
            : false;

        if (
            !$keyDetails
            || (
                $keyDetails['type'] === OPENSSL_KEYTYPE_RSA
                && ($keyDetails['bits'] ?? 0) < 2048
            )
            || !in_array(
                $keyDetails['type'],
                [OPENSSL_KEYTYPE_RSA, OPENSSL_KEYTYPE_EC],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'public_key_pem' => 'Provide a valid RSA (2048-bit minimum) or elliptic-curve public key.',
            ]);
        }

        $publicKeyHash = hash('sha256', $keyDetails['key']);

        // Diagnostic: identify the public key registered during enrollment.
        // This logs only a SHA-256 fingerprint of the public key,
        // never the actual key material.
        \Log::info('ATTENDSYNC biometric enrollment key diagnostic', [
            'public_key_hash' => $publicKeyHash,
            'key_type' => $keyDetails['type'] ?? null,
            'key_bits' => $keyDetails['bits'] ?? null,
        ]);

        if (BiometricCredential::where('public_key_hash', $publicKeyHash)->exists()) {
            throw ValidationException::withMessages([
                'public_key_pem' => 'This device key is already registered. Remove the existing credential or create a new device key.',
            ]);
        }

        $credential = BiometricCredential::create([
            'employee_id' => $employee->id,
            'credential_id' => (string) Str::uuid(),
            'verification_method' => $validated['verification_method'],
            'public_key_pem' => $validated['public_key_pem'],
            'public_key_hash' => $publicKeyHash,
            'device_name' => $validated['device_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Public key registration is pending biometric challenge verification.',
            'credential' => [
                'credential_id' => $credential->credential_id,
                'verification_method' => $credential->verification_method,
                'device_name' => $credential->device_name,
            ],
        ], 201);
    }

    public function status(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user->employee;

        if (!$employee || $user->role !== 'employee') {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $credentials = $employee->biometricCredentials()
            ->whereNotNull('verified_at')
            ->orderBy('verification_method')
            ->orderBy('created_at')
            ->get([
                'credential_id',
                'verification_method',
                'device_name',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'methods' => [
                'fingerprint' => [
                    'enrolled' => $credentials->contains(
                        'verification_method',
                        'fingerprint'
                    ),
                    'credentials' => $credentials
                        ->where('verification_method', 'fingerprint')
                        ->values(),
                ],
                'face' => [
                    'enrolled' => $credentials->contains(
                        'verification_method',
                        'face'
                    ),
                    'credentials' => $credentials
                        ->where('verification_method', 'face')
                        ->values(),
                ],
            ],
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'verification_method' => ['required', 'in:fingerprint'],
            'credential_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = auth('api')->user();
        $employee = $user->employee;

        if (!$employee || $user->role !== 'employee') {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $credentials = $employee->biometricCredentials()
            ->where('verification_method', $validated['verification_method']);

        if (!empty($validated['credential_id'])) {
            $credentials->where('credential_id', $validated['credential_id']);
        }

        $deleted = $credentials->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No matching biometric credential was found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Biometric credential(s) removed. Email and password login remains available.',
            'removed_count' => $deleted,
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_method' => ['required', 'in:fingerprint'],
            'credential_id' => ['required', 'string', 'uuid'],
            'purpose' => [
                'sometimes',
                'in:attendance-time-in,attendance-time-out,biometric-enrollment,time-in,time-out',
            ],
        ]);

        $purpose = match ($validated['purpose'] ?? 'attendance-time-in') {
            'time-in' => 'attendance-time-in',
            'time-out' => 'attendance-time-out',
            default => $validated['purpose'] ?? 'attendance-time-in',
        };

        $user = auth('api')->user();
        $employee = $user->employee;

        if (
            !$employee
            || $user->role !== 'employee'
            || $employee->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'An active employee account is required.',
            ], 403);
        }

        $credentialQuery = BiometricCredential::query()
            ->where('employee_id', $employee->id)
            ->where('credential_id', $validated['credential_id'])
            ->where(
                'verification_method',
                $validated['verification_method']
            );

        if ($purpose !== 'biometric-enrollment') {
            $credentialQuery->whereNotNull('verified_at');
        }

        $credential = $credentialQuery->first();

        if (!$credential) {
            return response()->json([
                'success' => false,
                'message' => 'Biometric credential not found.',
            ], 404);
        }

        $challengeId = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now(config('app.timezone'))->addMinutes(2);

        BiometricChallenge::create([
            'id' => $challengeId,
            'employee_id' => $employee->id,
            'biometric_credential_id' => $credential->id,
            'purpose' => $purpose,
            'nonce' => $nonce,
            'expires_at' => $expiresAt,
        ]);

        $message = $this->signedMessage(
            (string) $user->id,
            (string) $employee->id,
            $credential->verification_method,
            $challengeId,
            $nonce,
            $purpose
        );
        \Log::info('ATTENDSYNC challenge message diagnostic', [
            'challenge_id' => $challengeId,
            'message_hash' => hash('sha256', $message),
            'message_length' => strlen($message),
            'purpose' => $purpose,
        ]);

        return response()->json([
            'success' => true,
            'challenge_id' => $challengeId,
            'challenge' => $nonce,
            'message_to_sign' => $message,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function verify(Request $request): JsonResponse
{
    $validated = $request->validate([
        'challenge_id' => ['required', 'uuid'],
        'signature' => ['required', 'string', 'max:4096'],
    ]);

    $user = auth('api')->user();
    $employee = $user->employee;

    if (
        !$employee
        || $user->role !== 'employee'
        || $employee->status !== 'active'
    ) {
        return response()->json([
            'success' => false,
            'message' => 'An active employee account is required.',
        ], 403);
    }

    $signature = base64_decode($validated['signature'], true);

    if ($signature === false) {
        throw ValidationException::withMessages([
            'signature' => 'The signature must be valid base64.',
        ]);
    }

    return DB::transaction(
        function () use (
            $validated,
            $user,
            $employee,
            $signature
        ): JsonResponse {
            $now = Carbon::now(config('app.timezone'));

            $challenge = BiometricChallenge::query()
                ->with('credential')
                ->whereKey($validated['challenge_id'])
                ->where('employee_id', $employee->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', $now)
                ->lockForUpdate()
                ->first();

            if (!$challenge || !$challenge->credential) {
                return response()->json([
                    'success' => false,
                    'message' => 'Biometric challenge is invalid, expired, or already used.',
                ], 422);
            }

            $message = $this->signedMessage(
                (string) $user->id,
                (string) $employee->id,
                $challenge->credential->verification_method,
                $challenge->id,
                $challenge->nonce,
                $challenge->purpose
            );

            // Diagnostic: confirm that the server reconstructs
            // exactly the same message that was sent to the device.
            \Log::info('ATTENDSYNC verify message diagnostic', [
                'challenge_id' => $challenge->id,
                'message_hash' => hash('sha256', $message),
                'message_length' => strlen($message),
                'purpose' => $challenge->purpose,
            ]);

            $verifyResult = openssl_verify(
                $message,
                $signature,
                $challenge->credential->public_key_pem,
                OPENSSL_ALGO_SHA256
            );

            // Diagnostic: inspect the public key used during verification.
            // Only the SHA-256 fingerprint is logged.
            $verificationKey = openssl_pkey_get_public(
                $challenge->credential->public_key_pem
            );

            $verificationDetails = $verificationKey
                ? openssl_pkey_get_details($verificationKey)
                : false;

            \Log::info(
                'ATTENDSYNC biometric verification key diagnostic',
                [
                    'verify_result' => $verifyResult,
                    'signature_length' => strlen($signature),
                    'public_key_hash' => (
                        $verificationDetails
                        && isset($verificationDetails['key'])
                    )
                        ? hash(
                            'sha256',
                            $verificationDetails['key']
                        )
                        : null,
                    'key_type' => $verificationDetails['type'] ?? null,
                    'key_bits' => $verificationDetails['bits'] ?? null,
                ]
            );

            if ($verifyResult !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Biometric challenge signature is invalid.',
                ], 401);
            }

            $credential = BiometricCredential::query()
                ->whereKey($challenge->biometric_credential_id)
                ->lockForUpdate()
                ->firstOrFail();

            $challenge->consumed_at = $now;
            $challenge->save();

            if ($challenge->purpose === 'biometric-enrollment') {
                $credential->verified_at = $now;
                $credential->save();

                return response()->json([
                    'success' => true,
                    'credential_verified' => true,
                    'message' => 'Biometric public key was proven and enrollment is active.',
                ]);
            }

            if (!$credential->verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This biometric credential has not completed enrollment verification.',
                ], 422);
            }

            $token = bin2hex(random_bytes(32));

            BiometricVerification::create([
                'employee_id' => $employee->id,
                'biometric_credential_id' => $credential->id,
                'verification_method' => $credential->verification_method,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $now->copy()->addMinutes(5),
            ]);

            return response()->json([
                'success' => true,
                'credential_verified' => true,
                'message' => 'Signed challenge verified.',
                'biometric_verification_token' => $token,
                'verification_token' => $token,
                'expires_in' => 300,
            ]);
        }
    );
} 
private function signedMessage(
    string $userId,
    string $employeeId,
    string $method,
    string $challengeId,
    string $nonce,
    string $purpose
): string {
    return implode('|', [
        'ATTENDSYNC',
        $purpose,
        $userId,
        $employeeId,
        $method,
        $challengeId,
        $nonce,
    ]);
}
}