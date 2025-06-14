<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;

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

// Leave Quota Routes
Route::prefix('leave-quotas')->group(function () {
    Route::get('/', [App\Http\Controllers\LeaveQuotaController::class, 'index']);
    Route::post('/', [App\Http\Controllers\LeaveQuotaController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\LeaveQuotaController::class, 'destroy']);
});

// Leave Request Routes
Route::prefix('leave-requests')->group(function () {
    Route::get('/', [App\Http\Controllers\LeaveRequestController::class, 'index']);
    Route::post('/', [App\Http\Controllers\LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [App\Http\Controllers\LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [App\Http\Controllers\LeaveRequestController::class, 'reject']);
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
    });
});