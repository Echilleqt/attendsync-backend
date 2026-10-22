<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceBiometricCredential;
use App\Models\FaceVerificationToken;
use App\Services\DeviceProofService;
use App\Services\FaceRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FaceRecognitionController extends Controller
{
    public function __construct(
        protected FaceRecognitionService $faceRecognitionService,
        protected DeviceProofService $deviceProofService,
    ) {
    }

    public function status(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee') {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $credential = $employee->faceBiometricCredential()->first();
        $isEnrolled = (bool) ($credential && !empty($credential->face_template));

        return response()->json([
            'enrolled' => $isEnrolled,
            'face' => $credential ? [
                'verified_at' => $credential->verified_at?->toIso8601String(),
                'model_version' => $credential->model_version,
            ] : null,
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee' || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        // Prevent existing face template from being overwritten during device registration
        $existing = $employee->faceBiometricCredential()->first();
        if ($existing && !empty($existing->face_template)) {
            return response()->json([
                'success' => false,
                'message' => 'Face biometric template is already enrolled for this employee. Existing employees registering a new device must verify using face verification.',
            ], 409);
        }

        $file = $request->file('face_image')
            ?? $request->file('image')
            ?? $request->file('photo');

        if (!$file) {
            throw ValidationException::withMessages([
                'face_image' => 'A single face image is required for enrollment.',
            ]);
        }

        try {
            $this->faceRecognitionService->validateImage($file);
            $embedding = $this->faceRecognitionService->generateEmbedding($file->getRealPath());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'face_image' => $exception->getMessage(),
            ]);
        }

        $credential = FaceBiometricCredential::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'model_version' => $this->faceRecognitionService->getModelVersion(),
                'face_template' => $embedding,
                'verified_at' => now(),
                'enrollment_metadata' => [
                    'source' => 'api',
                    'enrolled_at' => now()->toIso8601String(),
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Face recognition enrolled successfully.',
            'face' => [
                'enrolled' => true,
                'verified_at' => $credential->verified_at?->toIso8601String(),
                'model_version' => $credential->model_version,
            ],
        ], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee' || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        $validated = $request->validate([
            'purpose' => ['nullable', 'string', 'in:attendance,device_registration'],
            'device_uuid' => ['nullable', 'string', 'max:191'],
            'public_key' => ['required_if:purpose,device_registration', 'nullable', 'string', 'max:4096'],
        ]);
        $purpose = $validated['purpose'] ?? 'attendance';
        $deviceUuid = (string) ($request->header('X-Device-Id') ?? '');

        if ($purpose === 'device_registration') {
            if (!$deviceUuid || ($validated['device_uuid'] ?? null) !== $deviceUuid) {
                throw ValidationException::withMessages([
                    'device_uuid' => 'The device identity does not match this request.',
                ]);
            }
            $this->deviceProofService->assertValid($request, $validated['public_key'], 'device_proof');
        }

        $credential = $employee->faceBiometricCredential()->first();

        if (!$credential || empty($credential->face_template)) {
            return response()->json([
                'verified' => false,
                'message' => 'Face recognition has not been enrolled for this employee.',
            ], 404);
        }

        $file = $request->file('face_image')
            ?? $request->file('image')
            ?? $request->file('photo');

        if (!$file) {
            throw ValidationException::withMessages([
                'face_image' => 'A single face image is required for verification.',
            ]);
        }

        try {
            $this->faceRecognitionService->validateImage($file);
            $embedding = $this->faceRecognitionService->generateEmbedding($file->getRealPath());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'face_image' => $exception->getMessage(),
            ]);
        }

        $threshold = $purpose === 'device_registration'
            ? (float) config('face-recognition.device_registration_threshold', 0.45)
            : (float) config('face-recognition.attendance_threshold', 0.45);
        $result = $this->faceRecognitionService->verificationResult(
            $credential->face_template,
            $embedding,
            $threshold
        );

        if ($result['verified']) {
            $token = Str::random(64);
            $expiresAt = now()->addMinutes((int) config('face-recognition.verification_ttl_minutes', 2));

            $requestedDeviceUuid = $validated['device_uuid'] ?? $request->header('X-Device-Id');
            if ((string) ($requestedDeviceUuid ?? '') !== $deviceUuid) {
                throw ValidationException::withMessages([
                    'device_uuid' => 'The device identity does not match this request.',
                ]);
            }

            FaceVerificationToken::query()->create([
                'employee_id' => $employee->id,
                'device_uuid' => $deviceUuid ?: $requestedDeviceUuid,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'verified' => true,
                'confidence' => $result['confidence'],
                'message' => 'Face verification successful.',
                'face_verification_token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        }

        return response()->json([
            'verified' => false,
            'confidence' => $result['confidence'],
            'message' => 'Face verification failed.',
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee || $user->role !== 'employee') {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $deleted = $employee->faceBiometricCredential()->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No face biometric credential is currently enrolled.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Face biometric credential removed successfully.',
        ]);
    }
}
