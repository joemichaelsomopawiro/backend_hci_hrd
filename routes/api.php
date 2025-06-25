<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\Api\GeneralAffairController;

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
    
    // Protected attendance routes with rate limiting
    Route::middleware(['attendance.rate.limit'])->group(function () {
        Route::post('/morning-reflections', [GeneralAffairController::class, 'storeMorningReflection']);
        Route::post('/zoom-join', [GeneralAffairController::class, 'recordZoomJoin']);
    });
    
    // Dashboard routes (read-only, no rate limiting needed)
    Route::get('/morning-reflections', [GeneralAffairController::class, 'getMorningReflections']);
    Route::get('/leaves', [GeneralAffairController::class, 'getLeaves']);
    
    // ========== NEW DASHBOARD GA ROUTES ==========
    // Bagian B: Dashboard GA - Tampilkan semua data absensi dan cuti
    Route::get('/dashboard/attendances', [GeneralAffairController::class, 'getAllAttendances']);
    Route::get('/dashboard/leave-requests', [GeneralAffairController::class, 'getAllLeaveRequests']);
    
    // Dashboard statistik
    Route::get('/dashboard/attendance-statistics', [GeneralAffairController::class, 'getAttendanceStatistics']);
    Route::get('/dashboard/leave-statistics', [GeneralAffairController::class, 'getLeaveStatistics']);
    
    // Bagian A: Riwayat kehadiran harian renungan pagi
    Route::get('/dashboard/morning-reflection-history', [GeneralAffairController::class, 'getDailyMorningReflectionHistory']);
});

// Leave Quota Routes
Route::prefix('leave-quotas')->group(function () {
    Route::get('/', [App\Http\Controllers\LeaveQuotaController::class, 'index']);
    Route::post('/', [App\Http\Controllers\LeaveQuotaController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'destroy']);
    
    // Endpoint untuk mendapatkan jatah cuti user yang sedang login
    Route::middleware('auth:sanctum')->get('/my-current', [App\Http\Controllers\LeaveQuotaController::class, 'getMyCurrentQuotas']);
    
    // Endpoint khusus untuk HR
    Route::post('/bulk-update', [App\Http\Controllers\LeaveQuotaController::class, 'bulkUpdate']);
    Route::post('/reset-annual', [App\Http\Controllers\LeaveQuotaController::class, 'resetAnnualQuotas']);
    Route::get('/usage-summary', [App\Http\Controllers\LeaveQuotaController::class, 'getUsageSummary']);
    // Tambahkan middleware auth:sanctum untuk endpoint ini
    Route::middleware('auth:sanctum')->get('/employees-without-quota', [App\Http\Controllers\LeaveQuotaController::class, 'getEmployeesWithoutQuota']);
});

// Leave Request Routes - Authorization handled by controller
Route::prefix('leave-requests')->middleware('auth:sanctum')->group(function () {
    // Semua authenticated user bisa melihat (filtering dilakukan di controller)
    Route::get('/', [App\Http\Controllers\LeaveRequestController::class, 'index']);
    
    // Semua authenticated user bisa mengajukan cuti (validasi role di controller)
    Route::post('/', [App\Http\Controllers\LeaveRequestController::class, 'store']);
    
    // Approve/reject cuti (validasi hierarki di controller)
    Route::put('/{id}/approve', [App\Http\Controllers\LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [App\Http\Controllers\LeaveRequestController::class, 'reject']);
    Route::delete('/{id}', [App\Http\Controllers\LeaveRequestController::class, 'destroy']);

    // Endpoint untuk HR melihat semua cuti yang sudah di-approve
    Route::get('/approved', [App\Http\Controllers\LeaveRequestController::class, 'getApprovedLeaves']);
    
    // Endpoint khusus untuk HR Dashboard - melihat SEMUA data cuti
    Route::get('/hr-dashboard', [App\Http\Controllers\LeaveRequestController::class, 'getAllLeavesForHR']);
    
    // Endpoint untuk Manager Dashboard
    Route::get('/manager-dashboard', [App\Http\Controllers\LeaveRequestController::class, 'getManagerDashboard']);
    
    // Endpoint untuk HR - Approved dan Rejected leaves
    Route::get('/hr/approved', [App\Http\Controllers\LeaveRequestController::class, 'getHRApprovedLeaves']);
    Route::get('/hr/rejected', [App\Http\Controllers\LeaveRequestController::class, 'getHRRejectedLeaves']);
    
    // Endpoint untuk Manager - Approved dan Rejected leaves
    Route::get('/manager/approved', [App\Http\Controllers\LeaveRequestController::class, 'getManagerApprovedLeaves']);
    Route::get('/manager/rejected', [App\Http\Controllers\LeaveRequestController::class, 'getManagerRejectedLeaves']);
});

// Attendance Routes
Route::prefix('attendances')->group(function () {
    Route::get('/', [App\Http\Controllers\AttendanceController::class, 'index']);
    Route::post('/', [App\Http\Controllers\AttendanceController::class, 'store']);
    Route::post('/check-in', [App\Http\Controllers\AttendanceController::class, 'checkIn']);
    Route::post('/check-out', [App\Http\Controllers\AttendanceController::class, 'checkOut']);
    Route::get('/summary', [App\Http\Controllers\AttendanceController::class, 'workHoursSummary']);
    Route::get('/dashboard', [App\Http\Controllers\AttendanceController::class, 'dashboard']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    // Register
    Route::post('/send-register-otp', [AuthController::class, 'sendRegisterOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/register', [AuthController::class, 'register']);
    
    // Login
    Route::post('/login', [AuthController::class, 'login']);
    
    // Forgot Password
    Route::post('/send-forgot-password-otp', [AuthController::class, 'sendForgotPasswordOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Profile Picture routes
        Route::post('/upload-profile-picture', [AuthController::class, 'uploadProfilePicture']);
        Route::delete('/delete-profile-picture', [AuthController::class, 'deleteProfilePicture']);
    });
});

// Manager routes - Commented out as functionality is handled by LeaveRequestController
/*
Route::middleware(['auth:sanctum'])->group(function () {
    // Routes untuk manager
    Route::prefix('manager')->group(function () {
        Route::get('/subordinates', [ManagerController::class, 'getSubordinates']);
        Route::get('/subordinates/{employeeId}', [ManagerController::class, 'getSubordinateDetail']);
        Route::get('/leave-requests', [ManagerController::class, 'getSubordinateLeaveRequests']);
        Route::post('/leave-requests/{leaveRequestId}/process', [ManagerController::class, 'processLeaveRequest']);
    });
});
*/