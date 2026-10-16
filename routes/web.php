<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDeviceController;
use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DtrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/login', [
    AuthController::class,
    'showLogin',
])->name('login');

Route::post('/login', [
    AuthController::class,
    'login',
])->name('login.submit');

Route::post('/logout', [
    AuthController::class,
    'logout',
])->name('logout');


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/dashboard', [
        AdminController::class,
        'dashboard',
    ])->name('admin.dashboard');


    /*
    |--------------------------------------------------------------------------
    | Employee Management
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/employees', [
        EmployeeController::class,
        'index',
    ])->name('admin.employees.index');

    Route::get('/admin/employees/create', [
        EmployeeController::class,
        'create',
    ])->name('admin.employees.create');

    Route::post('/admin/employees', [
        EmployeeController::class,
        'store',
    ])->name('admin.employees.store');

    Route::get('/admin/employees/{employee}/edit', [
        EmployeeController::class,
        'edit',
    ])->name('admin.employees.edit');

    Route::put('/admin/employees/{employee}', [
        EmployeeController::class,
        'update',
    ])->name('admin.employees.update');


    /*
    |--------------------------------------------------------------------------
    | Deactivate Employee
    |--------------------------------------------------------------------------
    */

    Route::post('/admin/employees/{employee}/deactivate', [
        EmployeeController::class,
        'deactivate',
    ])->name('admin.employees.deactivate');

    Route::post('/admin/employees/{employee}/activate', [
        EmployeeController::class,
        'activate',
    ])->name('admin.employees.activate');


    /*
    |--------------------------------------------------------------------------
    | Permanently Delete Employee
    |--------------------------------------------------------------------------
    */

    Route::delete('/admin/employees/{employee}', [
        EmployeeController::class,
        'destroy',
    ])->name('admin.employees.destroy');


    /*
    |--------------------------------------------------------------------------
    | Attendance Management
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/attendance', [
        AttendanceController::class,
        'index',
    ])->name('admin.attendance.index');

    Route::get('/admin/attendance/{attendance}/photo', [
        ApiAttendanceController::class,
        'photo',
    ])->name('admin.attendance.photo');

    Route::get('/admin/attendance-correction-requests', [
        AdminAttendanceCorrectionController::class,
        'index',
    ])->name('admin.attendance-correction-requests.index');

    Route::get('/admin/attendance-correction-requests/{correctionRequest}', [
        AdminAttendanceCorrectionController::class,
        'show',
    ])->name('admin.attendance-correction-requests.show');

    Route::post('/admin/attendance-correction-requests/{correctionRequest}/approve', [
        AdminAttendanceCorrectionController::class,
        'approve',
    ])->name('admin.attendance-correction-requests.approve');

    Route::post('/admin/attendance-correction-requests/{correctionRequest}/reject', [
        AdminAttendanceCorrectionController::class,
        'reject',
    ])->name('admin.attendance-correction-requests.reject');

     /*
|--------------------------------------------------------------------------
| Attendance Reports
|--------------------------------------------------------------------------
*/

Route::get('/admin/reports', [
    ReportController::class,
    'index',
])->name('admin.reports.index');


/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

Route::get('/admin/settings', [
    SettingsController::class,
    'index',
])->name('admin.settings.index');

Route::post('/admin/settings', [
    SettingsController::class,
    'store',
])->name('admin.settings.store');

Route::post('/admin/settings/network', [
    SettingsController::class,
    'updateNetwork',
])->name('admin.settings.network.update');

    /*
    |--------------------------------------------------------------------------
    | Approved Device Management (Step 4)
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/devices', [AdminDeviceController::class, 'index'])->name('admin.devices.index');
    Route::post('/admin/devices/{device}/approve', [AdminDeviceController::class, 'approve'])->name('admin.devices.approve');
    Route::post('/admin/devices/{device}/revoke', [AdminDeviceController::class, 'revoke'])->name('admin.devices.revoke');

    /*
    |--------------------------------------------------------------------------
    | Department Management (Step 5)
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
    Route::post('/admin/departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
    Route::put('/admin/departments/{department}', [DepartmentController::class, 'update'])->name('admin.departments.update');
    Route::delete('/admin/departments/{department}', [DepartmentController::class, 'destroy'])->name('admin.departments.destroy');

    /*
    |--------------------------------------------------------------------------
    | Schedule Management (Step 5)
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/schedules', [ScheduleController::class, 'index'])->name('admin.schedules.index');
    Route::post('/admin/schedules', [ScheduleController::class, 'store'])->name('admin.schedules.store');
    Route::put('/admin/schedules/{schedule}', [ScheduleController::class, 'update'])->name('admin.schedules.update');
    Route::delete('/admin/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('admin.schedules.destroy');

    /*
    |--------------------------------------------------------------------------
    | CSC Form No. 48 / Daily Time Record (DTR) (Step 5)
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/reports/dtr', [DtrController::class, 'index'])->name('admin.reports.dtr');
    Route::get('/admin/reports/dtr/print', [DtrController::class, 'print'])->name('admin.reports.dtr.print');

});


/*
|--------------------------------------------------------------------------
| Employee Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:employee'])->group(function () {

    Route::get('/employee/dashboard', function () {
        return view('employee.dashboard');
    })->name('employee.dashboard');


});