
<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BiometricVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/attendance/time-in', [
        AttendanceController::class,
        'timeIn',
    ]);

    Route::post('/attendance/time-out', [
        AttendanceController::class,
        'timeOut',
    ]);

    Route::get('/attendance/{attendance}/photo', [
        AttendanceController::class,
        'photo',
    ])->name('api.attendance.photo');

    Route::get('/attendance/{attendance}/time-out-photo', [
        AttendanceController::class,
        'timeOutPhoto',
    ])->name('api.attendance.time-out-photo');

    Route::get('/attendance/today', [
        AttendanceController::class,
        'today',
    ]);

    Route::post('/biometric/credentials', [
        BiometricVerificationController::class,
        'enroll',
    ]);

    Route::get('/biometric/credentials', [
        BiometricVerificationController::class,
        'status',
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