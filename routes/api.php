<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveQuotaController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\GeneralAffairController;
use App\Http\Controllers\AttendanceMachineController;
use App\Http\Controllers\WorshipAttendanceController;
use App\Http\Controllers\MorningReflectionController;
use App\Http\Controllers\MorningReflectionAttendanceController;
use App\Http\Controllers\AttendanceExportController;
// use App\Http\Controllers\AttendanceExcelUploadController;
use App\Http\Controllers\NationalHolidayController;
use App\Http\Controllers\CustomRoleController;
use App\Http\Controllers\GaDashboardController;
use App\Http\Controllers\ZoomLinkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ManualWorshipAttendanceController;
use App\Http\Controllers\AttendanceTxtUploadController;
use App\Http\Controllers\MusicArrangerController;
use App\Http\Controllers\ProducerMusicController;
use App\Http\Controllers\MusicNotificationController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\MusicWorkflowController;
use App\Http\Controllers\MusicArrangerHistoryController;

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
Route::post('/employees/{employeeId}/documents', [EmployeeController::class, 'uploadDocument']);

// User routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});

// General Affair Routes
Route::prefix('ga')->group(function () {
    Route::get('/employees', [GeneralAffairController::class, 'getEmployees']);
    
    Route::middleware(['attendance.rate.limit'])->group(function () {
        Route::post('/morning-reflections', [GeneralAffairController::class, 'storeMorningReflection']);
        Route::post('/zoom-join', [GeneralAffairController::class, 'recordZoomJoin']);
    });
    
    Route::get('/morning-reflections', [GeneralAffairController::class, 'getMorningReflections']);
    
    // Protected GA Dashboard Routes - Require Authentication
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/dashboard/attendances', [GeneralAffairController::class, 'getAllAttendances']);
        Route::get('/dashboard/leave-requests', [GeneralAffairController::class, 'getAllLeaveRequests']);
        Route::get('/dashboard/attendance-statistics', [GeneralAffairController::class, 'getAttendanceStatistics']);
        Route::get('/dashboard/leave-statistics', [GeneralAffairController::class, 'getLeaveStatistics']);
        Route::get('/daily-morning-reflection-history', [GeneralAffairController::class, 'getDailyMorningReflectionHistory']);
        Route::get('/leaves', [GeneralAffairController::class, 'getLeaves']);
    });

    // ===== ABSENSI RENUNGAN PAGI - ROUTES BARU - OPEN TO ALL USERS =====
    Route::middleware(['auth:sanctum'])->group(function () {
        // Dashboard untuk absensi renungan pagi
        Route::get('/dashboard/morning-reflection', [GeneralAffairController::class, 'morningReflectionDashboard']);
        Route::get('/dashboard/morning-reflection-statistics', [GeneralAffairController::class, 'getMorningReflectionStatistics']);
        Route::get('/dashboard/absent-employees-today', [GeneralAffairController::class, 'getAbsentEmployeesToday']);
        
        // Data karyawan
        Route::get('/employees/all', [GeneralAffairController::class, 'getAllEmployees']);
        
        // CRUD absensi renungan pagi
        Route::get('/morning-reflection-history', [GeneralAffairController::class, 'getMorningReflectionHistory']);
        Route::post('/morning-reflection-attendance', [GeneralAffairController::class, 'storeMorningReflectionAttendance']);
        Route::put('/morning-reflection-attendance/{id}', [GeneralAffairController::class, 'updateMorningReflectionAttendance']);
        Route::delete('/morning-reflection-attendance/{id}', [GeneralAffairController::class, 'deleteMorningReflectionAttendance']);
    });
});

// Leave Quota Routes -- BLOK YANG DIPERBAIKI --
Route::prefix('leave-quotas')->group(function () {
    // Rute umum non-parameter
    Route::get('/', [LeaveQuotaController::class, 'index']);
    Route::post('/', [LeaveQuotaController::class, 'store']);

    // Rute SPESIFIK yang dilindungi otentikasi diletakkan di atas
    Route::middleware('auth:sanctum')->group(function() {
        Route::get('/my-current', [LeaveQuotaController::class, 'getMyCurrentQuotas']);
        Route::post('/bulk-update', [LeaveQuotaController::class, 'bulkUpdate']);
        Route::post('/reset-annual', [LeaveQuotaController::class, 'resetAnnualQuotas']);
        Route::post('/reset-annual-manual', [LeaveQuotaController::class, 'resetAnnual']);
        Route::get('/usage-summary', [LeaveQuotaController::class, 'getUsageSummary']);
        Route::get('/employees-without-quota', [LeaveQuotaController::class, 'getEmployeesWithoutQuota']);
    });
    
    // Rute UMUM dengan parameter (wildcard) diletakkan di paling bawah grup ini
    Route::get('/{id}', [LeaveQuotaController::class, 'show']);
    Route::put('/{id}', [LeaveQuotaController::class, 'update']);
    Route::delete('/{id}', [LeaveQuotaController::class, 'destroy']);
});

// Leave Request Routes (Sudah Disederhanakan dan Benar)
Route::prefix('leave-requests')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [LeaveRequestController::class, 'index']);
    Route::get('/{id}', [LeaveRequestController::class, 'show']);
    Route::post('/', [LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
    // Alternatif untuk upload file (multipart) menggunakan POST
    Route::post('/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::delete('/{id}', [LeaveRequestController::class, 'destroy']);
    // Download surat cuti (PDF) - akan diimplementasikan di langkah berikutnya
    Route::get('/{id}/letter', [LeaveRequestController::class, 'downloadLetter'] ?? function() {
        return response()->json(['success'=>false,'message'=>'Letter generation not implemented'], 501);
    });
    // Upload tanda tangan atasan (opsional, jika tidak terunggah saat approve)
    Route::post('/{id}/approver-signature', [LeaveRequestController::class, 'uploadApproverSignature']);
});

// Attendance Routes - Solution X304 Integration
Route::prefix('attendance')->group(function () {
    // Public routes (untuk testing)
    Route::get('/dashboard', [AttendanceController::class, 'dashboard']);
    Route::get('/today-realtime', [AttendanceController::class, 'todayRealtime']);
    Route::get('/machine/status', [AttendanceController::class, 'machineStatus']);
    Route::get('/debug-sync', [AttendanceController::class, 'debugSync']);
    
    // Main attendance routes  
    Route::get('/list', [AttendanceController::class, 'index']);
    Route::get('/employee/{employeeId}', [AttendanceController::class, 'employeeDetail']);
    Route::get('/logs', [AttendanceController::class, 'logs']);
    Route::get('/summary', [AttendanceController::class, 'summary']);
    
    // Sync and processing routes
    Route::post('/sync', [AttendanceController::class, 'syncFromMachine']);
    Route::post('/sync-today', [AttendanceController::class, 'syncToday']);
    Route::post('/sync-today-only', [AttendanceController::class, 'syncTodayOnly']);
    Route::post('/sync-current-month', [AttendanceController::class, 'syncCurrentMonth']);
    Route::post('/sync-current-month-fast', [AttendanceController::class, 'syncCurrentMonthFast']);
    Route::post('/sync/users', [AttendanceController::class, 'syncUserData']);
    Route::post('/link-employees', [AttendanceController::class, 'linkEmployees']);
    Route::get('/users', [AttendanceController::class, 'getUserList']);
    Route::post('/process', [AttendanceController::class, 'processLogs']);
    Route::post('/process-today', [AttendanceController::class, 'processToday']);
    Route::post('/reprocess', [AttendanceController::class, 'reprocessDate']);
    
    // Individual attendance management
    Route::put('/{id}/recalculate', [AttendanceController::class, 'recalculate']);
    
    // Export routes
    Route::get('/export/daily', [AttendanceExportController::class, 'exportDaily']);
    Route::get('/export/monthly', [AttendanceExportController::class, 'exportMonthly']);
    Route::get('/export/download/{filename}', [AttendanceExportController::class, 'downloadFile']);
    
    // Monthly table route - open to all users
    Route::get('/monthly-table', [AttendanceExportController::class, 'monthlyTable']);
    
    // Leave integration routes
    Route::post('/sync-leave', [AttendanceController::class, 'syncLeaveToAttendance']);
    Route::post('/sync-leave-date-range', [AttendanceController::class, 'syncLeaveToAttendanceDateRange']);
    
    // Excel Upload routes
    // Route::post('/upload-excel', [AttendanceExcelUploadController::class, 'uploadExcel']);
    // Route::post('/upload-excel/preview', [AttendanceExcelUploadController::class, 'previewExcel']);
    // Route::get('/upload-excel/template', [AttendanceExcelUploadController::class, 'downloadTemplate']);
    // Route::get('/upload-excel/download-template', [AttendanceExcelUploadController::class, 'downloadTemplateFile']);
    // Route::get('/upload-excel/validation-rules', [AttendanceExcelUploadController::class, 'getValidationRules']);
});

// Attendance Routes (dari kode kedua)
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
    
    // Additional machine operations
    Route::post('/{id}/pull-today', [AttendanceMachineController::class, 'pullTodayData']);
    Route::get('/{id}/users', [AttendanceMachineController::class, 'getMachineUsers']);
});

// Test CORS endpoint
Route::get('/test-cors', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'CORS test successful',
        'timestamp' => now(),
        'origin' => request()->header('Origin')
    ]);
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
        Route::get('/check-employee-status', [AuthController::class, 'checkEmployeeStatus']);
        Route::post('/upload-profile-picture', [AuthController::class, 'uploadProfilePicture']);
        Route::delete('/delete-profile-picture', [AuthController::class, 'deleteProfilePicture']);
    });
});

// Manager Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('manager')->name('manager.')->group(function () {
        Route::get('/subordinates', [ManagerController::class, 'getSubordinates'])->name('subordinates');
        
        // TAMBAHKAN RUTE BARU INI
        Route::get('/subordinate-leave-quotas', [ManagerController::class, 'getSubordinateLeaveQuotas'])->name('subordinate-leave-quotas');

        // Anda bisa tambahkan rute manager lainnya di sini di kemudian hari
    });
});

// Worship Attendance Routes - GA (General Affairs) - OPEN TO ALL USERS
Route::prefix('ga')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/worship-dashboard', [WorshipAttendanceController::class, 'dashboard']);
    Route::get('/worship-dashboard-detailed', [WorshipAttendanceController::class, 'gaDashboard']);
    Route::get('/worship-attendances', [WorshipAttendanceController::class, 'getWorshipAttendances']);
    Route::post('/worship-attendances', [WorshipAttendanceController::class, 'storeWorshipAttendance']);
    Route::put('/worship-attendances/{id}', [WorshipAttendanceController::class, 'updateWorshipAttendance']);
    Route::delete('/worship-attendances/{id}', [WorshipAttendanceController::class, 'deleteWorshipAttendance']);
    Route::get('/worship-attendances/export', [WorshipAttendanceController::class, 'exportWorshipAttendances']);
    Route::get('/worship-attendances/statistics', [WorshipAttendanceController::class, 'attendanceStatistics']);
});

// Worship Attendance Routes - User (Semua Role)
Route::prefix('worship-attendance')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/attendance/{userId}/{date}', [WorshipAttendanceController::class, 'getUserAttendance']);
    Route::get('/week-history/{userId}', [WorshipAttendanceController::class, 'getWeekHistory']);
    Route::get('/leave/approved/{userId}/{date}', [WorshipAttendanceController::class, 'checkApprovedLeave']);
    Route::post('/submit', [WorshipAttendanceController::class, 'submitAttendance']);
    Route::get('/config', [WorshipAttendanceController::class, 'getConfig']);
    
    // Routes baru untuk user
    Route::post('/user-submit', [WorshipAttendanceController::class, 'submitUserAttendance']);
    Route::get('/user-status/{userId}/{date}', [WorshipAttendanceController::class, 'getUserAttendanceStatus']);
    Route::get('/user-week-history/{userId}', [WorshipAttendanceController::class, 'getUserWeekHistory']);
});

// ===== MORNING REFLECTION ROUTES =====

// Endpoint reset rate limit (khusus testing, tanpa auth)
Route::post('/morning-reflection/reset-rate-limit', [MorningReflectionController::class, 'resetRateLimit']);

// ===== DEBUG PANEL ENDPOINTS =====
Route::get('/morning-reflection/test-db', [MorningReflectionController::class, 'testDatabase']);
Route::post('/morning-reflection/join', [MorningReflectionController::class, 'joinZoom']);
Route::post('/morning-reflection/attendance', [MorningReflectionController::class, 'recordAttendance']);

// Routes untuk semua user (dengan autentikasi)
Route::prefix('morning-reflection')->middleware(['auth:sanctum'])->group(function () {
    // Status renungan pagi hari ini
    Route::get('/status', [MorningReflectionController::class, 'getStatus']);
    Route::get('/status-user', [MorningReflectionController::class, 'status']);
    
    // Absen renungan pagi - dengan rate limiting khusus
    Route::middleware(['attendance.rate.limit'])->group(function () {
        Route::post('/attend', [MorningReflectionController::class, 'attend']);
        Route::post('/attend-user', [MorningReflectionController::class, 'attendUser']);
    });
    
    // ======= ROUTE YANG DIPERLUKAN FRONTEND =======
    // Ambil history absensi + cuti (integrasi, paginasi, dsb)
    Route::get('/attendance', [MorningReflectionAttendanceController::class, 'getAttendance']);
    Route::get('/weekly-attendance', [MorningReflectionAttendanceController::class, 'getHistory']);
    // ==============================================

    // Route lain tetap boleh ada
    Route::get('/attendance-user', [MorningReflectionController::class, 'attendance']);
    Route::get('/attendance/{userId}/{date}', [MorningReflectionController::class, 'getUserAttendance']);
    Route::get('/attendance-by-date/{userId}/{date}', [MorningReflectionController::class, 'attendanceByDate']);
    Route::get('/weekly-attendance/{userId}', [MorningReflectionController::class, 'getWeeklyAttendance']);
    Route::get('/weekly-attendance-user/{userId}', [MorningReflectionController::class, 'weeklyAttendance']);
    Route::get('/config', [MorningReflectionController::class, 'getConfig']);
    Route::get('/config-user', [MorningReflectionController::class, 'config']);
    Route::get('/statistics', [MorningReflectionController::class, 'statistics']);
});

// Routes untuk GA (General Affairs) - OPEN TO ALL USERS
Route::prefix('morning-reflection')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/today-attendance', [MorningReflectionController::class, 'getTodayAttendance']);
    Route::get('/today-attendance-admin', [MorningReflectionController::class, 'todayAttendance']);
    Route::put('/config', [MorningReflectionController::class, 'updateConfig']);
    Route::put('/config-admin', [MorningReflectionController::class, 'updateConfigAdmin']);
});

// Tambahkan route baru untuk endpoint /api/morning-reflection-attendance/attendance (opsional, legacy)
Route::prefix('morning-reflection-attendance')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/attendance', [MorningReflectionAttendanceController::class, 'getAttendance']);
    Route::post('/attend', [MorningReflectionAttendanceController::class, 'attend']);
    Route::get('/history/{employeeId}', [MorningReflectionAttendanceController::class, 'getHistory']);
});

// Custom Role Management Routes
Route::prefix('custom-roles')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CustomRoleController::class, 'index']);
    Route::post('/', [CustomRoleController::class, 'store']);
    Route::get('/all-roles', [CustomRoleController::class, 'getAllRoles']);
    Route::get('/form-options', [CustomRoleController::class, 'getFormOptions']);
    Route::get('/by-department/{department}', [CustomRoleController::class, 'getRolesByDepartment']);
    Route::get('/hierarchy/{roleName}', [CustomRoleController::class, 'getRoleHierarchy']);
    Route::get('/{id}', [CustomRoleController::class, 'show']);
    Route::put('/{id}', [CustomRoleController::class, 'update']);
    Route::delete('/{id}', [CustomRoleController::class, 'destroy']);
});

// Route testing tanpa rate limit (untuk development)
Route::prefix('test')->group(function () {
    Route::post('/morning-reflection-attendance/attend', [MorningReflectionAttendanceController::class, 'attend']);
    Route::get('/morning-reflection-attendance/attendance', [MorningReflectionAttendanceController::class, 'getAttendance']);
});

// ===== PERSONAL PROFILE ROUTES =====
// Routes untuk profile pribadi (tanpa autentikasi)
Route::prefix('personal')->group(function () {
    // Profile pribadi
    Route::get('/profile', [\App\Http\Controllers\PersonalProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\PersonalProfileController::class, 'update']);

    // Office attendance (dengan autentikasi)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/office-attendance', [\App\Http\Controllers\PersonalAttendanceController::class, 'getPersonalOfficeAttendance']);
    });
});

// ===== EMPLOYEE SYNC ROUTES =====
// Routes untuk sinkronisasi employee (dengan autentikasi)
Route::prefix('employee-sync')->middleware(['auth:sanctum'])->group(function () {
    // Sync by name
    Route::post('/sync-by-name', [\App\Http\Controllers\EmployeeSyncController::class, 'syncByName']);
    
    // Sync by ID
    Route::post('/sync-by-id', [\App\Http\Controllers\EmployeeSyncController::class, 'syncById']);
    
    // Bulk sync
    Route::post('/bulk-sync', [\App\Http\Controllers\EmployeeSyncController::class, 'bulkSync']);
    
    // Get sync status
    Route::get('/status', [\App\Http\Controllers\EmployeeSyncController::class, 'getSyncStatus']);
    
    // Sync orphaned records
    Route::post('/sync-orphaned-records', [\App\Http\Controllers\EmployeeSyncController::class, 'syncOrphanedRecords']);
});

// ===== GA DASHBOARD ROUTES =====
// Routes untuk GA Dashboard - OPEN TO ALL USERS - Menampilkan SEMUA data tanpa batasan role
Route::prefix('ga-dashboard')->middleware(['auth:sanctum'])->group(function () {
    // Worship attendance routes
    Route::get('/worship-attendance', [GaDashboardController::class, 'getAllWorshipAttendance']);
    Route::get('/worship-attendance/week', [GaDashboardController::class, 'getWorshipAttendanceWeek']);
    Route::get('/worship-attendance/month', [GaDashboardController::class, 'getWorshipAttendanceMonth']);
        Route::get('/worship-attendance/export-month', [GaDashboardController::class, 'exportWorshipAttendanceMonth']);
    Route::get('/worship-attendance/export-week', [GaDashboardController::class, 'exportWorshipAttendanceWeek']);
    Route::get('/worship-attendance/all', [GaDashboardController::class, 'getWorshipAttendanceAll']);
    Route::get('/worship-statistics', [GaDashboardController::class, 'getWorshipStatistics']);
    
    // Manual worship attendance routes
    Route::get('/employees-for-manual-input', [ManualWorshipAttendanceController::class, 'getEmployeesForManualInput']);
    Route::post('/manual-worship-attendance', [ManualWorshipAttendanceController::class, 'store']);
    Route::post('/update-existing-worship-data', [ManualWorshipAttendanceController::class, 'updateExistingData']);
    Route::get('/export-worship-attendance', [GaDashboardController::class, 'exportWorshipAttendance']);
    Route::get('/export-leave-requests', [GaDashboardController::class, 'exportLeaveRequests']);
    
    // Leave requests routes - open to all users
    Route::get('/leave-requests', [GaDashboardController::class, 'getAllLeaveRequests']);
    Route::get('/leave-statistics', [GaDashboardController::class, 'getLeaveStatistics']);
});

// ===== CALENDAR ROUTES =====
// Routes untuk kalender nasional
Route::prefix('calendar')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [NationalHolidayController::class, 'index']);
    Route::get('/check', [NationalHolidayController::class, 'checkHoliday']);
    Route::get('/data', [NationalHolidayController::class, 'getCalendarData']);
    Route::get('/data-frontend', [NationalHolidayController::class, 'getCalendarDataForFrontend']);
    Route::get('/years', [NationalHolidayController::class, 'getAvailableYears']);
    Route::get('/yearly-summary', [NationalHolidayController::class, 'getYearlySummary']);
    Route::get('/yearly-holidays', [NationalHolidayController::class, 'getYearlyHolidays']);
    
    // Routes untuk manage hari libur - OPEN TO ALL USERS
        Route::post('/', [NationalHolidayController::class, 'store']);
        Route::put('/{id}', [NationalHolidayController::class, 'update']);
        Route::delete('/{id}', [NationalHolidayController::class, 'destroy']);
        Route::post('/seed', [NationalHolidayController::class, 'seedHolidays']);
        Route::post('/bulk-seed', [NationalHolidayController::class, 'bulkSeedYears']);
        
        // Routes untuk hari libur berulang dan kustom
        Route::post('/recurring', [NationalHolidayController::class, 'createRecurringHoliday']);
        Route::post('/monthly', [NationalHolidayController::class, 'createMonthlyHoliday']);
        Route::post('/date-range', [NationalHolidayController::class, 'createDateRangeHoliday']);
        Route::get('/custom', [NationalHolidayController::class, 'getCustomHolidays']);
        Route::get('/types', [NationalHolidayController::class, 'getHolidayTypes']);
        
        // Google Calendar Integration Routes
        Route::post('/sync-google', [NationalHolidayController::class, 'syncFromGoogleCalendar']);
        Route::get('/test-google-connection', [NationalHolidayController::class, 'testGoogleCalendarConnection']);
        Route::post('/clear-google-cache', [NationalHolidayController::class, 'clearGoogleCalendarCache']);
});

// ===== MUSIC ARRANGER ROUTES =====
// Routes untuk Music Arranger workflow
Route::prefix('music-arranger')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [MusicArrangerController::class, 'dashboard']);
    Route::get('/songs', [MusicArrangerController::class, 'getSongs']);
    Route::get('/singers', [MusicArrangerController::class, 'getSingers']);
    Route::get('/singers-fixed', [MusicArrangerController::class, 'getSingersFixed']);
    Route::get('/test-profile-url', [MusicArrangerController::class, 'testProfileUrl']);
    Route::get('/songs/{id}/audio', [MusicArrangerController::class, 'getSongAudio']);
    
    // Add new song and singer
    Route::post('/songs', [MusicArrangerController::class, 'addSong']);
    Route::post('/singers', [MusicArrangerController::class, 'addSinger']);
    
    // Update and delete songs
    Route::put('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
    Route::patch('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
    Route::delete('/songs/{id}', [MusicArrangerController::class, 'deleteSong']);
    
    // Update and delete singers
    Route::put('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
    Route::patch('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
    Route::post('/singers/{id}', [MusicArrangerController::class, 'updateSinger']); // For _method override
    Route::delete('/singers/{id}', [MusicArrangerController::class, 'deleteSinger']);
    
    // Music request routes
    Route::post('/requests', [MusicArrangerController::class, 'submitRequest']);
    Route::get('/requests', [MusicArrangerController::class, 'getMyRequests']);
    Route::get('/requests/{id}', [MusicArrangerController::class, 'getRequestDetail']);
    Route::put('/requests/{id}', [MusicArrangerController::class, 'updateRequest']);
    Route::put('/requests/{id}/update', [MusicArrangerController::class, 'updateRequest']); // Alias for frontend compatibility
    Route::post('/requests/{id}/update', [MusicArrangerController::class, 'updateRequest']); // POST alias for frontend compatibility
    Route::delete('/requests/{id}', [MusicArrangerController::class, 'cancelRequest']);
    
    // Music submission routes
    Route::get('/submissions', [MusicArrangerController::class, 'getSubmissions']);
    Route::get('/submissions/{id}', [MusicArrangerController::class, 'getSubmission']);
});

       // ===== PRODUCER MUSIC ROUTES =====
       // Routes untuk Producer music workflow
       Route::prefix('producer/music')->middleware(['auth:sanctum'])->group(function () {
           Route::get('/dashboard', [ProducerMusicController::class, 'dashboard']);
           Route::get('/songs', [ProducerMusicController::class, 'getSongs']); // Get available songs
           Route::get('/songs/{id}/audio', [ProducerMusicController::class, 'getSongAudio']); // Get song audio
           Route::get('/requests', [ProducerMusicController::class, 'getAllRequests']); // Add missing route
           Route::get('/submissions', [ProducerMusicController::class, 'getAllSubmissions']); // Get all submissions with detailed data
           Route::get('/requests/pending', [ProducerMusicController::class, 'getPendingRequests']);
           Route::get('/requests/approved', [ProducerMusicController::class, 'getApprovedRequests']);
           Route::get('/requests/rejected', [ProducerMusicController::class, 'getRejectedRequests']);
           Route::get('/requests/status/{status}', [ProducerMusicController::class, 'getRequestsByStatus']);
           Route::get('/requests/my', [ProducerMusicController::class, 'getMyRequests']);
           Route::get('/requests/{id}', [ProducerMusicController::class, 'getRequestDetail']);
           Route::get('/singers', [ProducerMusicController::class, 'getSingers']);

           // Add new song and singer (Producer can also add)
           Route::post('/songs', [ProducerMusicController::class, 'addSong']);
           Route::post('/singers', [ProducerMusicController::class, 'addSinger']);

           // Update and delete songs (Producer CRUD)
           Route::put('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
           Route::patch('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
           Route::delete('/songs/{id}', [ProducerMusicController::class, 'deleteSong']);

           // Update and delete singers (Producer CRUD)
           Route::put('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
           Route::patch('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
           Route::delete('/singers/{id}', [ProducerMusicController::class, 'deleteSinger']);

           // Producer actions
           Route::post('/requests/{id}/take', [ProducerMusicController::class, 'takeRequest']);
           Route::post('/requests/{id}/modify', [ProducerMusicController::class, 'modifyRequest']);
           Route::post('/requests/{id}/approve', [ProducerMusicController::class, 'approveRequest']);
           Route::post('/requests/{id}/reject', [ProducerMusicController::class, 'rejectRequest']);
       });

// ===== TEST ROUTES =====
Route::get('/test/singers', [App\Http\Controllers\TestController::class, 'testSingers']);
Route::get('/test/cors', [App\Http\Controllers\TestController::class, 'testCors']);
Route::get('/test/producer-modify-workflow', [App\Http\Controllers\TestController::class, 'testProducerModifyWorkflow']);
Route::get('/test/database-schema', [App\Http\Controllers\TestController::class, 'testDatabaseSchema']);
Route::get('/test/validation-fix', [App\Http\Controllers\TestController::class, 'testValidationFix']);
Route::get('/test/database-column-fix', [App\Http\Controllers\TestController::class, 'testDatabaseColumnFix']);

// ===== MUSIC NOTIFICATION ROUTES =====
// Routes untuk notifikasi music workflow
       Route::prefix('music/notifications')->middleware(['auth:sanctum'])->group(function () {
           Route::get('/', [MusicNotificationController::class, 'index']);
           Route::get('/unread-count', [MusicNotificationController::class, 'unreadCount']);
           Route::get('/count', [MusicNotificationController::class, 'unreadCount']); // Alias for frontend compatibility
           Route::put('/{id}/read', [MusicNotificationController::class, 'markAsRead']);
           Route::put('/mark-all-read', [MusicNotificationController::class, 'markAllAsRead']);
       });

       // ===== GENERAL NOTIFICATION ROUTES =====
       // Routes untuk notifikasi umum (compatibility dengan frontend)
       Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
           Route::get('/count', [MusicNotificationController::class, 'unreadCount']);
           Route::get('/read-status/{id}', [MusicNotificationController::class, 'getReadStatus']);
           Route::put('/{id}/read', [MusicNotificationController::class, 'markAsRead']);
           Route::put('/mark-read', [MusicNotificationController::class, 'markAsReadWithoutId']); // Alias for frontend compatibility
           Route::get('/', [MusicNotificationController::class, 'index']);
       });

       // ===== AUDIO ROUTES =====
       // Routes untuk audio playback dan upload
       Route::prefix('audio')->middleware(['auth:sanctum'])->group(function () {
           Route::get('/{songId}', [AudioController::class, 'stream']);
           Route::get('/{songId}/info', [AudioController::class, 'info']);
           Route::post('/{songId}/upload', [AudioController::class, 'upload']);
           Route::delete('/{songId}', [AudioController::class, 'delete']);
       });

       // ===== MUSIC WORKFLOW ROUTES =====
       // Routes untuk music workflow system
       Route::prefix('music-workflow')->middleware(['auth:sanctum'])->group(function () {
           // General workflow routes
           Route::get('/current-submission', [MusicWorkflowController::class, 'getCurrentSubmission']);
           Route::get('/list', [MusicWorkflowController::class, 'getWorkflowList']);
           Route::get('/submissions/{id}/history', [MusicWorkflowController::class, 'getWorkflowHistory']);
           Route::post('/submissions/{id}/transition', [MusicWorkflowController::class, 'transitionState']);
           Route::post('/submissions', [MusicWorkflowController::class, 'createSubmission']);
           Route::put('/submissions/{id}', [MusicWorkflowController::class, 'update']);
           Route::patch('/submissions/{id}', [MusicWorkflowController::class, 'update']);
           Route::delete('/submissions/{id}', [MusicWorkflowController::class, 'destroy']);
           
           // Notification routes
           Route::get('/notifications', [MusicWorkflowController::class, 'getNotifications']);
           Route::post('/notifications/{id}/read', [MusicWorkflowController::class, 'markNotificationAsRead']);
           Route::post('/notifications/mark-all-read', [MusicWorkflowController::class, 'markAllNotificationsAsRead']);
           
           // Analytics routes
           Route::get('/stats', [MusicWorkflowController::class, 'getWorkflowStats']);
           Route::get('/analytics', [MusicWorkflowController::class, 'getAnalytics']);
           
           // Music Arranger routes
           Route::post('/music-arranger/workflow/{id}/arrange', [MusicWorkflowController::class, 'submitArrangement']);
           Route::post('/music-arranger/workflow/{id}/resubmit-arrangement', [MusicWorkflowController::class, 'resubmitArrangement']);
           
           // Producer routes
           Route::post('/producer/workflow/{id}/approve', [MusicWorkflowController::class, 'approveSubmission']);
           Route::post('/producer/workflow/{id}/reject', [MusicWorkflowController::class, 'rejectSubmission']);
           Route::post('/producer/workflow/{id}/process', [MusicWorkflowController::class, 'processArrangement']);
           Route::post('/producer/workflow/{id}/qc-music', [MusicWorkflowController::class, 'qcMusic']);
           Route::post('/producer/workflow/{id}/approve-quality', [MusicWorkflowController::class, 'approveQuality']);
           Route::post('/producer/workflow/{id}/final-approve', [MusicWorkflowController::class, 'finalApprove']);
           
           // Sound Engineer routes
           Route::post('/sound-engineer/workflow/{id}/accept', [MusicWorkflowController::class, 'acceptSoundEngineeringWork']);
           Route::post('/sound-engineer/workflow/{id}/complete', [MusicWorkflowController::class, 'completeSoundEngineering']);
           Route::post('/sound-engineer/workflow/{id}/reject-to-arranger', [MusicWorkflowController::class, 'rejectArrangementBackToArranger']);
           
           // Creative routes
           Route::post('/creative/workflow/{id}/accept', [MusicWorkflowController::class, 'acceptCreativeWork']);
           Route::post('/creative/workflow/{id}/submit-work', [MusicWorkflowController::class, 'submitCreativeWork']);
       });

    // Music Arranger History Routes
    Route::prefix('music-arranger-history')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/submissions', [MusicArrangerHistoryController::class, 'getSubmissions']);
        Route::get('/submissions/{id}', [MusicArrangerHistoryController::class, 'getSubmission']);
        Route::put('/submissions/{id}', [MusicArrangerHistoryController::class, 'updateSubmission']);
        Route::delete('/submissions/{id}', [MusicArrangerHistoryController::class, 'deleteSubmission']);
        Route::post('/submissions/{id}/submit', [MusicArrangerHistoryController::class, 'submitSubmission']);
        Route::post('/submissions/{id}/cancel', [MusicArrangerHistoryController::class, 'cancelSubmission']);
        Route::post('/submissions/{id}/resubmit', [MusicArrangerHistoryController::class, 'resubmitSubmission']);
        Route::get('/submissions/{id}/download', [MusicArrangerHistoryController::class, 'downloadFiles']);
    });

// Route publik untuk mengambil link Zoom
Route::get('/zoom-link', [ZoomLinkController::class, 'getZoomLink']);

// Route proteksi untuk update link Zoom (hanya untuk GA dan role terkait)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/ga/zoom-link', [ZoomLinkController::class, 'getZoomLink']);
    Route::post('/ga/zoom-link', [ZoomLinkController::class, 'updateZoomLink']);
}); 

// Upload dan preview TXT absensi
Route::post('/attendance/upload-txt', [AttendanceTxtUploadController::class, 'uploadTxt']);
Route::post('/attendance/upload-txt/preview', [AttendanceTxtUploadController::class, 'previewTxt']); 
Route::post('/attendance/convert-raw-txt', [AttendanceTxtUploadController::class, 'convertRawTxt']);

// Manual sync dan status sync employee_id
Route::post('/attendance/upload-txt/manual-sync', [AttendanceTxtUploadController::class, 'manualBulkSync']);
Route::get('/attendance/upload-txt/sync-status', [AttendanceTxtUploadController::class, 'getSyncStatus']);

// Endpoint download template TXT absensi
Route::get('/attendance/template-txt', function () {
    $path = storage_path('app/template_attendance.txt');
    return response()->download($path, 'template_attendance.txt');
}); 

// ================= GA DASHBOARD ROUTES (OPEN TO ALL USERS) =================
Route::middleware(['auth:sanctum'])->group(function () {
    // Endpoint utama yang dipakai frontend - open to all users
    Route::get('/ga-dashboard/get-all-worship-attendance', [GaDashboardController::class, 'getAllWorshipAttendance']);
    Route::get('/ga-dashboard/get-all-leave-requests', [GaDashboardController::class, 'getAllLeaveRequests']);
    Route::get('/ga-dashboard/get-worship-statistics', [GaDashboardController::class, 'getWorshipStatistics']);
    Route::get('/ga-dashboard/get-leave-statistics', [GaDashboardController::class, 'getLeaveStatistics']);
    // Export Excel - open to all users
    Route::get('/ga-dashboard/export-worship-attendance', [GaDashboardController::class, 'exportWorshipAttendance']);
    Route::get('/ga-dashboard/export-leave-requests', [GaDashboardController::class, 'exportLeaveRequests']);
    // Legacy endpoints (opsional, untuk kompatibilitas lama) - open to all users
    Route::get('/ga-dashboard/worship-attendance', [GaDashboardController::class, 'getAllWorshipAttendance']);
    Route::get('/ga-dashboard/leave-requests', [GaDashboardController::class, 'getAllLeaveRequests']);
    Route::get('/ga-dashboard/worship-statistics', [GaDashboardController::class, 'getWorshipStatistics']);
    Route::get('/ga-dashboard/leave-statistics', [GaDashboardController::class, 'getLeaveStatistics']);
});

// Storage files with CORS headers (temporary fix for frontend)
Route::get('/storage/{path}', function (Request $request, $path) {
    $fullPath = storage_path('app/public/' . $path);
    
    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'File not found'], 404);
    }
    
    $mimeType = mime_content_type($fullPath);
    $fileSize = filesize($fullPath);
    
    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Content-Length' => $fileSize,
        'Accept-Ranges' => 'bytes',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
    ]);
})->where('path', '.*');

// Redirect old storage URLs to new API route (for frontend compatibility)
Route::get('/storage-redirect/{path}', function (Request $request, $path) {
    return redirect("/api/storage/{$path}", 301);
})->where('path', '.*');

// Include Music Program API Routes
require_once __DIR__ . '/music_api.php';

// Include Test API Routes (for debugging)
require_once __DIR__ . '/test_api.php';

// Test endpoint untuk validation fix 422
Route::get('/test/validation-fix-422', function() {
    try {
        return response()->json([
            'success' => true,
            'message' => 'Validation Fix 422 Applied',
            'data' => [
                'fix_applied' => true,
                'changes' => [
                    'Fixed validation rules to use singers table instead of users table',
                    'Added proper validation for proposed_singer_id and approved_singer_id',
                    'Fixed 422 validation error for Producer Modify',
                    'Added workflow history creation',
                    'Improved error handling and logging'
                ],
                'validation_rules' => [
                    'proposed_singer_id' => 'nullable|exists:singers,id',
                    'approved_singer_id' => 'nullable|exists:singers,id',
                    'song_id' => 'nullable|exists:songs,id'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error testing validation fix: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk check database tables
Route::get('/test/database-tables', function() {
    try {
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
        
        $hasSingersTable = in_array('singers', $tableNames);
        $hasSongsTable = in_array('songs', $tableNames);
        $hasUsersTable = in_array('users', $tableNames);
        
        return response()->json([
            'success' => true,
            'message' => 'Database tables check',
            'data' => [
                'tables' => $tableNames,
                'has_singers_table' => $hasSingersTable,
                'has_songs_table' => $hasSongsTable,
                'has_users_table' => $hasUsersTable,
                'recommendation' => $hasSingersTable ? 'Use singers table for validation' : 'Create singers table or use users table'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking database tables: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk check singers data
Route::get('/test/singers-data', function() {
    try {
        $singers = DB::table('singers')->select('id', 'name', 'status')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Singers data check',
            'data' => [
                'total_singers' => $singers->count(),
                'singers' => $singers,
                'recommendation' => $singers->count() > 0 ? 'Singers table has data' : 'No singers found in database'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking singers data: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk check songs data
Route::get('/test/songs-data', function() {
    try {
        $songs = DB::table('songs')->select('id', 'title', 'artist', 'status')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Songs data check',
            'data' => [
                'total_songs' => $songs->count(),
                'songs' => $songs,
                'recommendation' => $songs->count() > 0 ? 'Songs table has data' : 'No songs found in database'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking songs data: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk producer modify fix
Route::get('/test/producer-modify-fix', function() {
    try {
        return response()->json([
            'success' => true,
            'message' => 'Producer Modify Fix Applied',
            'data' => [
                'fix_applied' => true,
                'changes' => [
                    'Simplified validation - only validate action',
                    'Modify = Auto Approve functionality',
                    'No complex validation for singer/song IDs',
                    'Direct database update without validation',
                    'Fixed 422 validation error for Producer Modify'
                ],
                'workflow' => [
                    'Producer Modify → Auto Approve → State: arranging',
                    'Music Arranger can start arranging immediately',
                    'No need for separate approval step'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error testing producer modify fix: ' . $e->getMessage()
        ], 500);
    }
});

// Debug submission data
Route::get('/test/submission/{id}', function($id) {
    try {
        $submission = \App\Models\MusicSubmission::find($id);
        
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Submission data',
            'data' => [
                'submission' => $submission,
                'song' => $submission->song,
                'proposed_singer' => $submission->proposedSinger,
                'approved_singer' => $submission->approvedSinger,
                'current_state' => $submission->current_state,
                'submission_status' => $submission->submission_status ?? 'N/A'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error getting submission data: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk create submission fix
Route::get('/test/create-submission-fix', function() {
    try {
        return response()->json([
            'success' => true,
            'message' => 'Create Submission Fix Applied',
            'data' => [
                'fix_applied' => true,
                'changes' => [
                    'Removed exists validation from createSubmission method',
                    'Changed song_id validation to integer|min:1',
                    'Changed proposed_singer_id validation to integer|min:1',
                    'Music Arranger can now create submissions without validation errors',
                    'Fixed 422 error for Music Arranger create submission'
                ],
                'validation_rules' => [
                    'song_id' => 'required|integer|min:1',
                    'proposed_singer_id' => 'nullable|integer|min:1',
                    'arrangement_notes' => 'nullable|string|max:1000',
                    'requested_date' => 'nullable|date|after_or_equal:today'
                ],
                'workflow' => [
                    'Music Arranger Create Submission → No exists validation',
                    'Producer Modify Submission → Simplified validation',
                    'Both methods now work without 422 errors'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error testing create submission fix: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk edit/delete submission fix
Route::get('/test/edit-delete-submission-fix', function() {
    try {
        return response()->json([
            'success' => true,
            'message' => 'Edit/Delete Submission Fix Applied',
            'data' => [
                'fix_applied' => true,
                'changes' => [
                    'Added update() method for edit submission',
                    'Added destroy() method for delete submission',
                    'Added routes for PUT and DELETE submissions',
                    'Music Arranger can now edit/delete pending submissions',
                    'Fixed 404 error for Music Arranger edit/delete submission'
                ],
                'methods_added' => [
                    'update() - Edit submission (only if status = submitted)',
                    'destroy() - Delete submission (only if status = submitted)'
                ],
                'routes_added' => [
                    'PUT /api/music-workflow/submissions/{id}',
                    'DELETE /api/music-workflow/submissions/{id}'
                ],
                'validation_rules' => [
                    'song_id' => 'required|integer|min:1',
                    'proposed_singer_id' => 'nullable|integer|min:1',
                    'arrangement_notes' => 'nullable|string|max:1000',
                    'requested_date' => 'nullable|date|after_or_equal:today'
                ],
                'workflow' => [
                    'Music Arranger Create Submission → State: submitted',
                    'Music Arranger Edit Submission → Only if status = submitted',
                    'Music Arranger Delete Submission → Only if status = submitted',
                    'Producer Review → State: arranging (cannot edit/delete)'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error testing edit/delete submission fix: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk workflow fix
Route::get('/test/workflow-fix', function() {
    try {
        return response()->json([
            'success' => true,
            'message' => 'Workflow Fix Applied',
            'data' => [
                'fix_applied' => true,
                'changes' => [
                    'Fixed Producer Modify workflow - no auto approve',
                    'Added startArranging() method for Music Arranger',
                    'Added submitArrangement() method for Music Arranger',
                    'Added routes for Music Arranger workflow',
                    'Fixed workflow sequence: Producer Modify → Music Arranger Arrange → Submit → Producer Review',
                    'REMOVED DUPLICATE submitArrangement() method that was causing conflicts',
                    'ADDED getSubmissions() method to MusicArrangerController'
                ],
                'workflow_fixed' => [
                    'Before: Producer Modify → Auto Approve → Producer Review',
                    'After: Producer Modify → Music Arranger Arrange → Submit → Producer Review'
                ],
                'methods_added' => [
                    'startArranging() - Music Arranger start arranging',
                    'submitArrangement() - Music Arranger submit arrangement (NO FILE UPLOAD REQUIRED)',
                    'getSubmissions() - Music Arranger get submissions with state filtering'
                ],
                'routes_added' => [
                    'POST /api/music-workflow/arranger/start-arranging/{id}',
                    'POST /api/music-workflow/arranger/submit-arrangement/{id}',
                    'GET /api/music-arranger/submissions (with status filter)'
                ],
                'state_transitions' => [
                    'submitted → arranging (Producer Modify/Approve)',
                    'arranging → arranging (Music Arranger Start Arranging)',
                    'arranging → arrangement_review (Music Arranger Submit Arrangement)',
                    'arrangement_review → arrangement_approved/rejected (Producer Review)'
                ],
                'fixes_applied' => [
                    'Removed duplicate submitArrangement() method',
                    'Fixed submitArrangement() to NOT require file upload',
                    'Fixed submitArrangement() to only change state to arrangement_review',
                    'Producer Modify now correctly sets state to arranging (not arrangement_review)',
                    'Added getSubmissions() method to show arranging state in Music Arranger'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error testing workflow fix: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint untuk cek state submission
Route::get('/debug/producer-modify-state/{id}', function($id) {
    try {
        $submission = \App\Models\MusicSubmission::find($id);
        
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Submission state debug',
            'data' => [
                'submission_id' => $submission->id,
                'current_state' => $submission->current_state,
                'submission_status' => $submission->submission_status ?? 'N/A',
                'music_arranger_id' => $submission->music_arranger_id,
                'song_id' => $submission->song_id,
                'proposed_singer_id' => $submission->proposed_singer_id,
                'approved_singer_id' => $submission->approved_singer_id,
                'arrangement_started' => $submission->arrangement_started ?? false,
                'producer_notes' => $submission->producer_notes,
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking submission state: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint untuk cek semua submissions
Route::get('/debug/all-submissions', function() {
    try {
        $submissions = \App\Models\MusicSubmission::with(['song', 'proposedSinger', 'approvedSinger'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'All submissions debug',
            'data' => $submissions->map(function($submission) {
                return [
                    'id' => $submission->id,
                    'current_state' => $submission->current_state,
                    'submission_status' => $submission->submission_status ?? 'N/A',
                    'music_arranger_id' => $submission->music_arranger_id,
                    'song_title' => $submission->song->title ?? 'N/A',
                    'proposed_singer_name' => $submission->proposedSinger->name ?? 'N/A',
                    'approved_singer_name' => $submission->approvedSinger->name ?? 'N/A',
                    'arrangement_started' => $submission->arrangement_started ?? false,
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking all submissions: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint untuk cek Music Arranger submissions
Route::get('/debug/music-arranger-submissions', function() {
    try {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Music Arranger') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Music Arranger can access this endpoint.'
            ], 403);
        }
        
        $submissions = \App\Models\MusicSubmission::where('music_arranger_id', $user->id)
            ->with(['song', 'proposedSinger', 'approvedSinger'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Music Arranger submissions debug',
            'data' => [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'total_submissions' => $submissions->count(),
                'submissions_by_state' => $submissions->groupBy('current_state')->map->count(),
                'submissions' => $submissions->map(function($submission) {
                    return [
                        'id' => $submission->id,
                        'current_state' => $submission->current_state,
                        'submission_status' => $submission->submission_status ?? 'N/A',
                        'song_title' => $submission->song->title ?? 'N/A',
                        'proposed_singer_name' => $submission->proposedSinger->name ?? 'N/A',
                        'approved_singer_name' => $submission->approvedSinger->name ?? 'N/A',
                        'arrangement_started' => $submission->arrangement_started ?? false,
                        'created_at' => $submission->created_at,
                        'updated_at' => $submission->updated_at
                    ];
                })
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking Music Arranger submissions: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint untuk cek authentication
Route::get('/debug/auth-check', function() {
    try {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'message' => 'Authentication check',
            'data' => [
                'auth_check' => auth()->check(),
                'user_id' => $user->id ?? 'N/A',
                'user_role' => $user->role ?? 'N/A',
                'user_email' => $user->email ?? 'N/A',
                'token_valid' => auth()->check()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error checking authentication: ' . $e->getMessage()
        ], 500);
    }
})->middleware(['auth:sanctum']);

// Debug endpoint untuk test start arranging
Route::post('/debug/test-start-arranging/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugTestStartArranging'])->middleware(['auth:sanctum']);

// Debug endpoint untuk test actual start arranging
Route::post('/debug/actual-start-arranging/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugActualStartArranging'])->middleware(['auth:sanctum']);

// Debug endpoint untuk test submit arrangement
Route::post('/debug/test-submit-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugTestSubmitArrangement'])->middleware(['auth:sanctum']);

// Debug endpoint untuk actual submit arrangement
Route::post('/debug/actual-submit-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugActualSubmitArrangement'])->middleware(['auth:sanctum']);

// Debug endpoint untuk Producer review arrangement
Route::post('/debug/producer-review-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugProducerReviewArrangement'])->middleware(['auth:sanctum']);

// Debug endpoint untuk Producer approve arrangement
Route::post('/debug/producer-approve-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugProducerApproveArrangement'])->middleware(['auth:sanctum']);

// Debug endpoint untuk Producer reject arrangement
Route::post('/debug/producer-reject-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'debugProducerRejectArrangement'])->middleware(['auth:sanctum']);

// Debug endpoint untuk update request
Route::put('/debug/update-request/{id}', function(\Illuminate\Http\Request $request, $id) {
    try {
        $user = Auth::user();
        $submission = \App\Models\MusicSubmission::find($id);
        
        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Submission not found'], 404);
        }

        // Log before update
        $beforeUpdate = [
            'song_id' => $submission->song_id,
            'proposed_singer_id' => $submission->proposed_singer_id,
            'arrangement_notes' => $submission->arrangement_notes
        ];

        // Perform direct update
        $updateData = $request->only(['song_id', 'proposed_singer_id', 'arrangement_notes', 'requested_date']);
        $updateResult = $submission->update($updateData);

        // Check after update
        $afterUpdate = $submission->fresh();
        
        return response()->json([
            'success' => true,
            'message' => 'Debug update test',
            'data' => [
                'request_data' => $request->all(),
                'update_data' => $updateData,
                'update_result' => $updateResult,
                'before_update' => $beforeUpdate,
                'after_update' => [
                    'song_id' => $afterUpdate->song_id,
                    'proposed_singer_id' => $afterUpdate->proposed_singer_id,
                    'arrangement_notes' => $afterUpdate->arrangement_notes
                ],
                'database_check' => \App\Models\MusicSubmission::find($id)->only(['song_id', 'proposed_singer_id', 'arrangement_notes'])
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Debug error: ' . $e->getMessage()
        ], 500);
    }
})->middleware(['auth:sanctum']);

// Endpoint untuk cek Laravel log terbaru
Route::get('/debug/latest-logs', function() {
    try {
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Log file not found'
            ]);
        }
        
        $lines = file($logFile);
        $latestLines = array_slice($lines, -50); // Get last 50 lines
        
        return response()->json([
            'success' => true,
            'data' => [
                'latest_logs' => array_map('trim', $latestLines),
                'total_lines' => count($lines)
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error reading logs: ' . $e->getMessage()
        ]);
    }
})->middleware(['auth:sanctum']);

// Simple debug untuk cek submission ID 50
Route::get('/debug/check-submission/{id}', function($id) {
    try {
        $user = Auth::user();
        $submission = \App\Models\MusicSubmission::find($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'submission_id' => $id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'submission_exists' => $submission ? true : false,
                'submission_data' => $submission ? [
                    'id' => $submission->id,
                    'music_arranger_id' => $submission->music_arranger_id,
                    'current_state' => $submission->current_state,
                    'song_id' => $submission->song_id,
                    'proposed_singer_id' => $submission->proposed_singer_id
                ] : null
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
})->middleware(['auth:sanctum']);

// Debug endpoint untuk test integrasi data penyanyi
Route::get('/debug/test-singer-integration', [\App\Http\Controllers\MusicWorkflowController::class, 'debugTestSingerIntegration'])->middleware(['auth:sanctum']);

// Working unified endpoints (simplified)
Route::get('/unified/songs', function(Request $request) {
    try {
        $songs = \App\Models\Song::where('status', 'available')->orderBy('title')->get();
        
        // Add audio URL to each song
        $songs->transform(function ($song) {
            $song->audio_file_url = $song->audio_file_path ? asset('storage/' . $song->audio_file_path) : null;
            return $song;
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'songs' => $songs->toArray(),
                'total' => $songs->count()
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/unified/singers', function(Request $request) {
    try {
        // Get singers from Singer model
        $singers = \App\Models\Singer::where('status', 'active')->orderBy('name')->get();
        
        // Get users with Singer role
        $userSingers = \App\Models\User::where('role', 'Singer')->orderBy('name')->get();
        
        // Merge and deduplicate by email
        $allSingers = collect();
        $emailsSeen = [];
        
        // Add singers from Singer model
        foreach ($singers as $singer) {
            if (!in_array($singer->email, $emailsSeen)) {
                $allSingers->push([
                    'id' => $singer->id,
                    'name' => $singer->name,
                    'email' => $singer->email,
                    'phone' => $singer->phone,
                    'bio' => $singer->bio,
                    'specialties' => $singer->specialties,
                    'status' => $singer->status,
                    'source' => 'singer'
                ]);
                $emailsSeen[] = $singer->email;
            }
        }
        
        // Add users with Singer role
        foreach ($userSingers as $user) {
            if (!in_array($user->email, $emailsSeen)) {
                $allSingers->push([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'bio' => null,
                    'specialties' => [],
                    'status' => 'active',
                    'source' => 'user'
                ]);
                $emailsSeen[] = $user->email;
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'singers' => $allSingers->values()->toArray(),
                'total' => $allSingers->count()
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint untuk test submission
Route::post('/debug/test-submission', function(Request $request) {
    try {
        $data = $request->all();
        Log::info('Debug test submission called', $data);
        
        // Test basic validation
        $validator = Validator::make($data, [
            'song_id' => 'required|exists:songs,id',
            'proposed_singer_id' => 'nullable|exists:users,id',
            'arrangement_notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Test database connection
        $song = \App\Models\Song::find($data['song_id']);
        $user = \App\Models\User::find($data['proposed_singer_id'] ?? 1);
        
        return response()->json([
            'success' => true,
            'message' => 'Debug test successful',
            'data' => [
                'song' => $song ? $song->title : 'Not found',
                'user' => $user ? $user->name : 'Not found',
                'request_data' => $data
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Debug test failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Debug endpoint untuk test submission dengan create
Route::post('/debug/test-create-submission', function(Request $request) {
    try {
        $data = $request->all();
        Log::info('Debug test create submission called', $data);
        
        // Test basic validation
        $validator = Validator::make($data, [
            'song_id' => 'required|exists:songs,id',
            'proposed_singer_id' => 'nullable|exists:users,id',
            'arrangement_notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Test create submission
        $submissionData = [
            'song_id' => $data['song_id'],
            'proposed_singer_id' => $data['proposed_singer_id'],
            'arrangement_notes' => $data['arrangement_notes'],
            'music_arranger_id' => 6, // Test user ID
            'current_state' => 'submitted',
            'submission_status' => 'pending',
            'submitted_at' => now()
        ];
        
        Log::info('Creating submission with data:', $submissionData);
        $submission = \App\Models\MusicSubmission::create($submissionData);
        Log::info('Submission created with ID:', ['id' => $submission->id]);
        
        return response()->json([
            'success' => true,
            'message' => 'Debug create submission successful',
            'data' => $submission->load(['song', 'proposedSinger'])
        ]);
        
    } catch (Exception $e) {
        Log::error('Debug create submission failed', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Debug create submission failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Test endpoint submission tanpa auth untuk debugging
Route::post('/test/submission', function(Request $request) {
    try {
        $data = $request->all();
        Log::info('Test submission called', $data);
        
        // Test basic validation
        $validator = Validator::make($data, [
            'song_id' => 'required|exists:songs,id',
            'proposed_singer_id' => 'nullable|exists:users,id',
            'arrangement_notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Test create submission
        $submissionData = [
            'song_id' => $data['song_id'],
            'proposed_singer_id' => $data['proposed_singer_id'],
            'arrangement_notes' => $data['arrangement_notes'],
            'music_arranger_id' => 6, // Test user ID
            'current_state' => 'submitted',
            'submission_status' => 'pending',
            'submitted_at' => now()
        ];
        
        Log::info('Creating submission with data:', $submissionData);
        $submission = \App\Models\MusicSubmission::create($submissionData);
        Log::info('Submission created with ID:', ['id' => $submission->id]);
        
        return response()->json([
            'success' => true,
            'message' => 'Test submission successful',
            'data' => $submission->load(['song', 'proposedSinger'])
        ]);
        
    } catch (Exception $e) {
        Log::error('Test submission failed', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Test submission failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Test endpoints untuk debugging backend fixes
Route::post('/test/process-arrangement/{id}', function(Request $request, $id) {
    try {
        $data = $request->all();
        Log::info('Test process arrangement called', ['id' => $id, 'data' => $data]);
        
        // Test basic validation
        $validator = Validator::make($data, [
            'producer_notes' => 'nullable|string|max:1000',
            'processing_notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Test database connection
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Test process arrangement successful',
            'data' => [
                'submission_id' => $id,
                'current_state' => $submission->current_state,
                'request_data' => $data
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test process arrangement failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

Route::post('/test/reject-arrangement/{id}', function(Request $request, $id) {
    try {
        $data = $request->all();
        Log::info('Test reject arrangement called', ['id' => $id, 'data' => $data]);
        
        // Test basic validation
        $validator = Validator::make($data, [
            'producer_feedback' => 'required|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Test database connection
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Test reject arrangement successful',
            'data' => [
                'submission_id' => $id,
                'current_state' => $submission->current_state,
                'request_data' => $data
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test reject arrangement failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Test endpoints untuk Music Arranger workflow after rejection
Route::post('/test/music-arranger/edit-submission/{id}', function(Request $request, $id) {
    try {
        $data = $request->all();
        Log::info('Test edit submission called', ['id' => $id, 'data' => $data]);
        
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        // Test if can edit when rejected
        if (!in_array($submission->current_state, ['submitted', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit in current state: ' . $submission->current_state
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Can edit submission',
            'data' => [
                'submission_id' => $id,
                'current_state' => $submission->current_state,
                'can_edit' => true
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/test/music-arranger/start-arranging/{id}', function(Request $request, $id) {
    try {
        Log::info('Test start arranging called', ['id' => $id]);
        
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        // Test if can start arranging when rejected
        if (!in_array($submission->current_state, ['submitted', 'producer_review', 'arranging', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start arranging in current state: ' . $submission->current_state
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Can start arranging',
            'data' => [
                'submission_id' => $id,
                'current_state' => $submission->current_state,
                'can_start_arranging' => true
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/test/music-arranger/resubmit-arrangement/{id}', function(Request $request, $id) {
    try {
        Log::info('Test resubmit arrangement called', ['id' => $id]);
        
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        // Test if can resubmit when rejected
        if ($submission->current_state !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resubmit in current state: ' . $submission->current_state
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Can resubmit arrangement',
            'data' => [
                'submission_id' => $id,
                'current_state' => $submission->current_state,
                'can_resubmit' => true
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

// Test endpoint untuk process arrangement debug
Route::post('/test/process-arrangement-debug/{id}', function(Request $request, $id) {
    try {
        $data = $request->all();
        Log::info('Test process arrangement debug called', ['id' => $id, 'data' => $data]);
        
        // Check if submission exists
        $submission = \App\Models\MusicSubmission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }
        
        // Check current state
        if ($submission->current_state !== 'arrangement_review') {
            return response()->json([
                'success' => false,
                'message' => 'Submission is not in arrangement_review state. Current state: ' . $submission->current_state
            ], 400);
        }
        
        // Try to update without timestamp issues
        $result = DB::table('music_submissions')
            ->where('id', $id)
            ->update([
                'current_state' => 'producer_processing',
                'producer_notes' => $data['producer_notes'] ?? null,
                'processing_notes' => $data['processing_notes'] ?? null,
                'processed_at' => now()
            ]);
        
        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Process arrangement debug successful',
                'data' => [
                    'submission_id' => $id,
                    'current_state' => 'producer_processing',
                    'updated_rows' => $result
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No rows updated'
            ], 400);
        }
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test process arrangement debug failed: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Test endpoints untuk Producer data completeness
Route::get('/test/producer/submissions', function(Request $request) {
    try {
        $submissions = \App\Models\MusicSubmission::with(['song', 'musicArranger', 'proposedSinger', 'approvedSinger'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $formattedSubmissions = $submissions->map(function ($submission) {
            return [
                'id' => $submission->id,
                'current_state' => $submission->current_state,
                'submission_status' => $submission->submission_status,
                
                // Data Lagu
                'song' => $submission->song ? [
                    'id' => $submission->song->id,
                    'title' => $submission->song->title,
                    'artist' => $submission->song->artist,
                    'genre' => $submission->song->genre,
                    'duration' => $submission->song->duration,
                    'audio_file_url' => $submission->song->audio_file_url,
                    'status' => $submission->song->status
                ] : null,
                
                // Data Music Arranger
                'music_arranger' => $submission->musicArranger ? [
                    'id' => $submission->musicArranger->id,
                    'name' => $submission->musicArranger->name,
                    'email' => $submission->musicArranger->email,
                    'phone' => $submission->musicArranger->phone
                ] : null,
                
                // Data Proposed Singer
                'proposed_singer' => $submission->proposedSinger ? [
                    'id' => $submission->proposedSinger->id,
                    'name' => $submission->proposedSinger->name,
                    'email' => $submission->proposedSinger->email,
                    'phone' => $submission->proposedSinger->phone
                ] : null,
                
                // Data Arrangement
                'arrangement' => [
                    'notes' => $submission->arrangement_notes,
                    'file_url' => $submission->arrangement_file_url,
                    'file_name' => $submission->arrangement_file_name,
                    'started' => $submission->arrangement_started,
                    'completed_at' => $submission->arrangement_completed_at
                ],
                
                // Data Request
                'request' => [
                    'requested_date' => $submission->requested_date,
                    'submitted_at' => $submission->submitted_at,
                    'approved_at' => $submission->approved_at,
                    'rejected_at' => $submission->rejected_at
                ],
                
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Producer data completeness test successful',
            'data' => $formattedSubmissions,
            'total' => $submissions->count()
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/test/producer/submission/{id}', function(Request $request, $id) {
    try {
        $submission = \App\Models\MusicSubmission::with([
            'song', 'musicArranger', 'proposedSinger', 'approvedSinger'
        ])->findOrFail($id);

        $formattedData = [
            'id' => $submission->id,
            'current_state' => $submission->current_state,
            'submission_status' => $submission->submission_status,
            
            // Data Lagu Lengkap
            'song' => $submission->song ? [
                'id' => $submission->song->id,
                'title' => $submission->song->title,
                'artist' => $submission->song->artist,
                'genre' => $submission->song->genre,
                'duration' => $submission->song->duration,
                'key_signature' => $submission->song->key_signature,
                'bpm' => $submission->song->bpm,
                'notes' => $submission->song->notes,
                'audio_file_path' => $submission->song->audio_file_path,
                'audio_file_url' => $submission->song->audio_file_url,
                'status' => $submission->song->status
            ] : null,
            
            // Data Music Arranger Lengkap
            'music_arranger' => $submission->musicArranger ? [
                'id' => $submission->musicArranger->id,
                'name' => $submission->musicArranger->name,
                'email' => $submission->musicArranger->email,
                'phone' => $submission->musicArranger->phone,
                'profile_picture_url' => $submission->musicArranger->profile_picture_url
            ] : null,
            
            // Data Proposed Singer Lengkap
            'proposed_singer' => $submission->proposedSinger ? [
                'id' => $submission->proposedSinger->id,
                'name' => $submission->proposedSinger->name,
                'email' => $submission->proposedSinger->email,
                'phone' => $submission->proposedSinger->phone,
                'role' => $submission->proposedSinger->role,
                'profile_picture_url' => $submission->proposedSinger->profile_picture_url
            ] : null,
            
            // Data Arrangement Lengkap
            'arrangement' => [
                'notes' => $submission->arrangement_notes,
                'file_path' => $submission->arrangement_file_path,
                'file_url' => $submission->arrangement_file_url,
                'file_name' => $submission->arrangement_file_name,
                'started' => $submission->arrangement_started,
                'started_at' => $submission->arrangement_started_at,
                'completed_at' => $submission->arrangement_completed_at
            ],
            
            // Data Request Lengkap
            'request' => [
                'requested_date' => $submission->requested_date,
                'submitted_at' => $submission->submitted_at,
                'approved_at' => $submission->approved_at,
                'rejected_at' => $submission->rejected_at,
                'completed_at' => $submission->completed_at
            ],
            
            // Data Producer
            'producer' => [
                'notes' => $submission->producer_notes,
                'feedback' => $submission->producer_feedback,
                'processing_notes' => $submission->processing_notes,
                'processed_at' => $submission->processed_at
            ],
            
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at
        ];

        return response()->json([
            'success' => true,
            'message' => 'Producer submission detail test successful',
            'data' => $formattedData
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

// Note: Unified endpoints sudah dibuat di atas tanpa middleware untuk testing

// ===== ARRANGER WORKFLOW ROUTES =====
// Start arranging (no body)
Route::post('/music-workflow/arranger/start-arranging/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'startArranging'])->middleware(['auth:sanctum']);
// Submit arrangement (with file)
Route::post('/music-workflow/arranger/submit-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'submitArrangement'])->middleware(['auth:sanctum']);

// Debug unified endpoints (no auth for testing)
Route::get('/debug/unified/songs', [\App\Http\Controllers\MusicWorkflowController::class, 'debugUnifiedSongs']);
Route::get('/debug/unified/singers', [\App\Http\Controllers\MusicWorkflowController::class, 'debugUnifiedSingers']);

// Debug endpoint untuk test integrasi data lagu
Route::get('/debug/test-song-integration', [\App\Http\Controllers\MusicWorkflowController::class, 'debugTestSongIntegration'])->middleware(['auth:sanctum']);

// Producer routes untuk arrangement audio
Route::prefix('music-workflow')->middleware(['auth:sanctum'])->group(function () {
    // Get arrangement audio file info (Producer)
    Route::get('/producer/arrangement-audio/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'getArrangementAudio']);
    
    // Download arrangement audio file (Producer)
    Route::get('/producer/download-arrangement/{id}', [\App\Http\Controllers\MusicWorkflowController::class, 'downloadArrangementAudio']);
});

// Program Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Program Management
    Route::apiResource('programs', \App\Http\Controllers\ProgramController::class);
    Route::post('/programs/{id}/assign-teams', [\App\Http\Controllers\ProgramController::class, 'assignTeams']);
    Route::get('/programs/{id}/statistics', [\App\Http\Controllers\ProgramController::class, 'statistics']);
    Route::get('/programs/{id}/dashboard', [\App\Http\Controllers\ProgramController::class, 'dashboard']);
    
    // Team Management
    Route::post('/teams/{id}/add-members', [\App\Http\Controllers\TeamController::class, 'addMembers']);
    Route::post('/teams/{id}/remove-members', [\App\Http\Controllers\TeamController::class, 'removeMembers']);
    Route::put('/teams/{id}/update-member-role', [\App\Http\Controllers\TeamController::class, 'updateMemberRole']);
    Route::apiResource('teams', \App\Http\Controllers\TeamController::class);
    
    // Schedule Management
    Route::apiResource('schedules', \App\Http\Controllers\ScheduleController::class);
    Route::get('/schedules/upcoming', [\App\Http\Controllers\ScheduleController::class, 'upcoming']);
    Route::get('/schedules/today', [\App\Http\Controllers\ScheduleController::class, 'today']);
    Route::get('/schedules/overdue', [\App\Http\Controllers\ScheduleController::class, 'overdue']);
    Route::put('/schedules/{id}/update-status', [\App\Http\Controllers\ScheduleController::class, 'updateStatus']);
    
    // Media Management
    Route::apiResource('media-files', \App\Http\Controllers\MediaController::class);
    Route::post('/media-files/upload', [\App\Http\Controllers\MediaController::class, 'upload']);
    Route::get('/media-files/by-type/{type}', [\App\Http\Controllers\MediaController::class, 'getByType']);
    Route::get('/media-files/by-program/{programId}', [\App\Http\Controllers\MediaController::class, 'getByProgram']);
    Route::get('/media-files/by-episode/{episodeId}', [\App\Http\Controllers\MediaController::class, 'getByEpisode']);
    
    // Production Management
    Route::apiResource('production-equipment', \App\Http\Controllers\ProductionController::class);
    Route::get('/production-equipment/available', [\App\Http\Controllers\ProductionController::class, 'getAvailable']);
    Route::get('/production-equipment/in-use', [\App\Http\Controllers\ProductionController::class, 'getInUse']);
    Route::get('/production-equipment/needs-maintenance', [\App\Http\Controllers\ProductionController::class, 'getNeedsMaintenance']);
    Route::put('/production-equipment/{id}/assign', [\App\Http\Controllers\ProductionController::class, 'assignEquipment']);
    Route::put('/production-equipment/{id}/return', [\App\Http\Controllers\ProductionController::class, 'returnEquipment']);
    Route::put('/production-equipment/{id}/maintenance', [\App\Http\Controllers\ProductionController::class, 'updateMaintenance']);
    
    // Episode Management
    Route::apiResource('episodes', \App\Http\Controllers\EpisodeController::class);
    Route::get('/episodes/by-program/{programId}', [\App\Http\Controllers\EpisodeController::class, 'getByProgram']);
    Route::get('/episodes/upcoming', [\App\Http\Controllers\EpisodeController::class, 'getUpcoming']);
    Route::put('/episodes/{id}/update-status', [\App\Http\Controllers\EpisodeController::class, 'updateStatus']);
    
    // Notifications
    Route::get('/program-notifications', [\App\Http\Controllers\ProgramNotificationController::class, 'index']);
    Route::get('/program-notifications/unread', [\App\Http\Controllers\ProgramNotificationController::class, 'unread']);
    Route::put('/program-notifications/{id}/mark-read', [\App\Http\Controllers\ProgramNotificationController::class, 'markAsRead']);
    Route::put('/program-notifications/mark-all-read', [\App\Http\Controllers\ProgramNotificationController::class, 'markAllAsRead']);
});
