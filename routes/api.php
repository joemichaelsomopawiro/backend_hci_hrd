<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveQuotaController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\GeneralAffairController;
use App\Http\Controllers\AttendanceMachineController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// All routes without authentication
Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/{id}', [EmployeeController::class, 'show']);
Route::post('/employees', [EmployeeController::class, 'store']);
Route::put('/employees/{id}', [EmployeeController::class, 'update']);
Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
Route::delete('/employees/{employeeId}/documents/{documentId}', [EmployeeController::class, 'deleteDocument']);
Route::delete('/employees/{employeeId}/employment-histories/{historyId}', [EmployeeController::class, 'deleteEmploymentHistory']);
Route::delete('/employees/{employeeId}/promotion-histories/{promotionId}', [EmployeeController::class, 'deletePromotionHistory']);
Route::delete('/employees/{employeeId}/trainings/{trainingId}', [EmployeeController::class, 'deleteTraining']);
Route::delete('/employees/{employeeId}/benefits/{benefitId}', [EmployeeController::class, 'deleteBenefit']);

// General Affair Routes
Route::prefix('ga')->group(function () {
    Route::get('/employees', [GeneralAffairController::class, 'getEmployees']);
    
    Route::middleware(['attendance.rate.limit'])->group(function () {
        Route::post('/morning-reflections', [GeneralAffairController::class, 'storeMorningReflection']);
        Route::post('/zoom-join', [GeneralAffairController::class, 'recordZoomJoin']);
    });
    
    Route::get('/morning-reflections', [GeneralAffairController::class, 'getMorningReflections']);
    Route::get('/dashboard/attendances', [GeneralAffairController::class, 'getAllAttendances']);
    Route::get('/dashboard/leave-requests', [GeneralAffairController::class, 'getAllLeaveRequests']);
    Route::get('/dashboard/attendance-statistics', [GeneralAffairController::class, 'getAttendanceStatistics']);
    Route::get('/dashboard/leave-statistics', [GeneralAffairController::class, 'getLeaveStatistics']);
    Route::get('/daily-morning-reflection-history', [GeneralAffairController::class, 'getDailyMorningReflectionHistory']);
    Route::get('/leaves', [GeneralAffairController::class, 'getLeaves']);
});

// Leave Quota Routes
Route::prefix('leave-quotas')->group(function () {
    Route::get('/', [LeaveQuotaController::class, 'index']);
    Route::post('/', [LeaveQuotaController::class, 'store']);
    Route::get('/{id}', [LeaveQuotaController::class, 'show']);
    Route::put('/{id}', [LeaveQuotaController::class, 'update']);
    Route::delete('/{id}', [LeaveQuotaController::class, 'destroy']);
    
    Route::middleware('auth:sanctum')->group(function() {
        // PERUBAHAN DI SINI: dari /my-current menjadi /current
        Route::get('/current', [LeaveQuotaController::class, 'getMyCurrentQuotas']);
        
        Route::post('/bulk-update', [LeaveQuotaController::class, 'bulkUpdate']);
        Route::post('/reset-annual', [LeaveQuotaController::class, 'resetAnnualQuotas']);
        Route::get('/usage-summary', [LeaveQuotaController::class, 'getUsageSummary']);
        Route::get('/employees-without-quota', [LeaveQuotaController::class, 'getEmployeesWithoutQuota']);
    });
});

// Leave Request Routes (Sudah Disederhanakan dan Benar)
Route::prefix('leave-requests')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [LeaveRequestController::class, 'index']);
    Route::post('/', [LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::delete('/{id}', [LeaveRequestController::class, 'destroy']);
});

// Attendance Routes
Route::prefix('attendances')->group(function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::post('/', [AttendanceController::class, 'store']);
    Route::post('/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/summary', [AttendanceController::class, 'workHoursSummary']);
    Route::get('/dashboard', [AttendanceController::class, 'dashboard']);
});

// Attendance Machine Management Routes
Route::prefix('attendance-machines')->middleware('auth:sanctum')->group(function () {
    // CRUD operations
    Route::get('/', [AttendanceMachineController::class, 'index']);
    Route::post('/', [AttendanceMachineController::class, 'store']);
    Route::get('/{id}', [AttendanceMachineController::class, 'show']);
    Route::put('/{id}', [AttendanceMachineController::class, 'update']);
    Route::delete('/{id}', [AttendanceMachineController::class, 'destroy']);
    
    // Machine operations
    Route::post('/{id}/test-connection', [AttendanceMachineController::class, 'testConnection']);
    Route::post('/{id}/pull-attendance', [AttendanceMachineController::class, 'pullAttendanceData']);
    Route::post('/{id}/pull-attendance-process', [AttendanceMachineController::class, 'pullAndProcessAttendanceData']);
    
    // User synchronization
    Route::post('/{id}/sync-user/{employeeId}', [AttendanceMachineController::class, 'syncSpecificUser']);
    Route::post('/{id}/sync-all-users', [AttendanceMachineController::class, 'syncAllUsers']);
    Route::delete('/{id}/remove-user/{employeeId}', [AttendanceMachineController::class, 'removeUser']);
    
    // Machine management
    Route::post('/{id}/restart', [AttendanceMachineController::class, 'restartMachine']);
    Route::post('/{id}/clear-data', [AttendanceMachineController::class, 'clearAttendanceData']);
    Route::post('/{id}/sync-time', [AttendanceMachineController::class, 'syncTime']);
    
    // Logs and monitoring
    Route::get('/{id}/sync-logs', [AttendanceMachineController::class, 'getSyncLogs']);
    Route::get('/dashboard', [AttendanceMachineController::class, 'getDashboard']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/send-register-otp', [AuthController::class, 'sendRegisterOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-forgot-password-otp', [AuthController::class, 'sendForgotPasswordOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/upload-profile-picture', [AuthController::class, 'uploadProfilePicture']);
        Route::delete('/delete-profile-picture', [AuthController::class, 'deleteProfilePicture']);
    });
});

/*
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('manager')->group(function () {
        Route::get('/subordinates', [ManagerController::class, 'getSubordinates']);
        // ...
    });
});
*/

// Attendance Machine Management Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Attendance Machines
    Route::prefix('attendance-machines')->group(function () {
        Route::get('/', [AttendanceMachineController::class, 'index']);
        Route::post('/', [AttendanceMachineController::class, 'store']);
        Route::get('/{id}', [AttendanceMachineController::class, 'show']);
        Route::put('/{id}', [AttendanceMachineController::class, 'update']);
        Route::delete('/{id}', [AttendanceMachineController::class, 'destroy']);
        
        // Machine Operations
        Route::post('/{id}/test-connection', [AttendanceMachineController::class, 'testConnection']);
        Route::post('/{id}/pull-attendance', [AttendanceMachineController::class, 'pullAttendanceData']);
        Route::post('/{id}/sync-users', [AttendanceMachineController::class, 'syncAllUsers']);
        Route::post('/{id}/restart', [AttendanceMachineController::class, 'restart']);
        Route::post('/{id}/clear-data', [AttendanceMachineController::class, 'clearData']);
        Route::post('/{id}/sync-time', [AttendanceMachineController::class, 'syncTime']);
        
        // Sync Logs
        Route::get('/{id}/sync-logs', [AttendanceMachineController::class, 'syncLogs']);
    });
});