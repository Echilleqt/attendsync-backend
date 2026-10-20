
<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BiometricVerificationController;
use App\Http\Controllers\Api\DeviceRegistrationController;
use App\Http\Controllers\Api\FaceRecognitionController;
use App\Http\Controllers\Api\NetworkPolicyController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'network.restriction'])->group(function () {

    Route::get('/network-policy', [NetworkPolicyController::class, 'policy']);
    Route::post('/network-verify', [NetworkPolicyController::class, 'verify']);
    Route::get('/network-restriction/policy', [NetworkPolicyController::class, 'policy']);
    Route::post('/network-restriction/verify', [NetworkPolicyController::class, 'verify']);

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Device Registration & Authorization (Step 4)
    Route::post('/devices/register', [DeviceRegistrationController::class, 'register']);
    Route::get('/devices/status', [DeviceRegistrationController::class, 'status']);

    // Protected employee endpoints requiring an approved registered device
    Route::middleware('device.approved')->group(function () {
        // Attendance Actions
        Route::post('/attendance/time-in', [
            AttendanceController::class,
            'timeIn',
        ]);

        Route::post('/attendance/time-out', [
            AttendanceController::class,
            'timeOut',
        ]);

        Route::post('/attendance/record', [
            AttendanceController::class,
            'record',
        ]);

        Route::post('/attendance/morning-in', [
            AttendanceController::class,
            'morningIn',
        ]);

        Route::post('/attendance/morning-out', [
            AttendanceController::class,
            'morningOut',
        ]);

        Route::post('/attendance/afternoon-in', [
            AttendanceController::class,
            'afternoonIn',
        ]);

        Route::post('/attendance/afternoon-out', [
            AttendanceController::class,
            'afternoonOut',
        ]);

        Route::get('/attendance/{attendance}/photo', [
            AttendanceController::class,
            'photo',
        ])->name('api.attendance.photo');

        Route::get('/attendance/{attendance}/time-out-photo', [
            AttendanceController::class,
            'timeOutPhoto',
        ])->name('api.attendance.time-out-photo');

        Route::get('/attendance/{attendance}/photo/{entry}', [
            AttendanceController::class,
            'entryPhoto',
        ])->name('api.attendance.entry-photo');

        Route::get('/attendance/today', [
            AttendanceController::class,
            'today',
        ]);

        Route::get('/attendance/history', [
            AttendanceController::class,
            'history',
        ]);

        Route::get('/attendance/dtr', [
            AttendanceController::class,
            'dtr',
        ]);

        Route::post('/biometric/credentials', [
            BiometricVerificationController::class,
            'enroll',
        ]);

        Route::delete('/biometric/credentials', [
            BiometricVerificationController::class,
            'revoke',
        ]);

        Route::post('/biometric/challenges', [
            BiometricVerificationController::class,
            'challenge',
        ])->middleware('throttle:10,1');

        Route::post('/biometric/verify', [
            BiometricVerificationController::class,
            'verify',
        ])->middleware('throttle:10,1');

        Route::delete('/face/revoke', [
            FaceRecognitionController::class,
            'revoke',
        ]);

        Route::get('/attendance-correction-requests', [
            AttendanceCorrectionController::class,
            'indexMine',
        ]);

        Route::post('/attendance-correction-requests', [
            AttendanceCorrectionController::class,
            'store',
        ]);

        Route::get('/attendance-correction-requests/{correctionRequest}', [
            AttendanceCorrectionController::class,
            'showMine',
        ]);
    });

    // Device and account management allowed while pending/unregistered
    Route::get('/biometric/credentials', [
        BiometricVerificationController::class,
        'status',
    ]);

    Route::get('/face/status', [
        FaceRecognitionController::class,
        'status',
    ]);

    Route::post('/face/enroll', [
        FaceRecognitionController::class,
        'enroll',
    ])->middleware('throttle:10,1');

    Route::post('/face/verify', [
        FaceRecognitionController::class,
        'verify',
    ])->middleware('throttle:10,1');

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/attendance-correction-requests', [
            AttendanceCorrectionController::class,
            'indexAdmin',
        ]);

        Route::get('/attendance-correction-requests/{correctionRequest}', [
            AttendanceCorrectionController::class,
            'showAdmin',
        ]);

        Route::post('/attendance-correction-requests/{correctionRequest}/approve', [
            AttendanceCorrectionController::class,
            'approve',
        ]);

        Route::post('/attendance-correction-requests/{correctionRequest}/reject', [
            AttendanceCorrectionController::class,
            'reject',
        ]);
    });
});