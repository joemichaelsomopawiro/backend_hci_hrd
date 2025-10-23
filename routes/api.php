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
use App\Http\Controllers\ReminderNotificationController;
use App\Http\Controllers\TeamManagementController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\FileManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\ArtSetPropertiController;
use App\Http\Controllers\ProgramAnalyticsController;

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

// Public route: download surat cuti tanpa autentikasi
Route::get('/leave-requests/{id}/letter', [LeaveRequestController::class, 'downloadLetter']);

// Leave Request Routes (Sudah Disederhanakan dan Benar)
Route::prefix('leave-requests')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [LeaveRequestController::class, 'index']);
    Route::get('/{id}', [LeaveRequestController::class, 'show']);
    Route::post('/', [LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
    // Alternatif untuk upload file (multipart) menggunakan POST
    Route::post('/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
    // Tambahkan alternatif method POST agar kompatibel dengan FE lama
    Route::post('/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::delete('/{id}', [LeaveRequestController::class, 'destroy']);
    // Download surat cuti (PDF) dipindah menjadi route publik di luar middleware
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

// Program Management System Routes
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProgramNotificationController;

// Programs Routes
Route::apiResource('programs', ProgramController::class);
Route::post('/programs/{program}/assign-teams', [ProgramController::class, 'assignTeams']);
Route::get('/programs/{program}/dashboard', [ProgramController::class, 'dashboard']);
Route::get('/programs/{program}/statistics', [ProgramController::class, 'statistics']);

// Teams Routes
Route::post('/teams/{id}/add-members', [TeamController::class, 'addMembers']);
Route::post('/teams/{id}/remove-members', [TeamController::class, 'removeMembers']);
Route::put('/teams/{id}/update-member-role', [TeamController::class, 'updateMemberRole']);
Route::apiResource('teams', TeamController::class);

// Episodes Routes
Route::apiResource('episodes', EpisodeController::class);
Route::patch('/episodes/{episode}/update-status', [EpisodeController::class, 'updateStatus']);
Route::get('/episodes/upcoming', [EpisodeController::class, 'getUpcoming']);
Route::get('/episodes/aired', [EpisodeController::class, 'getAired']);
Route::get('/episodes/by-program/{programId}', [EpisodeController::class, 'getByProgram']);

// Schedules Routes
Route::apiResource('schedules', ScheduleController::class);
Route::patch('/schedules/{schedule}/update-status', [ScheduleController::class, 'updateStatus']);
Route::get('/schedules/upcoming', [ScheduleController::class, 'getUpcoming']);
Route::get('/schedules/today', [ScheduleController::class, 'getToday']);
Route::get('/schedules/overdue', [ScheduleController::class, 'getOverdue']);

// Media Files Routes
Route::apiResource('media-files', MediaController::class);
Route::post('/media-files/upload', [MediaController::class, 'upload']);
Route::get('/media-files/type/{type}', [MediaController::class, 'getByType']);
Route::get('/media-files/program/{programId}', [MediaController::class, 'getByProgram']);
Route::get('/media-files/episode/{episodeId}', [MediaController::class, 'getByEpisode']);

// Production Equipment Routes
Route::apiResource('production-equipment', ProductionController::class);
Route::post('/production-equipment/{equipment}/assign', [ProductionController::class, 'assign']);
Route::post('/production-equipment/{equipment}/unassign', [ProductionController::class, 'unassign']);
Route::get('/production-equipment/available', [ProductionController::class, 'getAvailable']);
Route::get('/production-equipment/needs-maintenance', [ProductionController::class, 'getNeedsMaintenance']);

// Program Notifications Routes
Route::get('/program-notifications/unread-count', [ProgramNotificationController::class, 'getUnreadCount']);
Route::get('/program-notifications/unread', [ProgramNotificationController::class, 'unread']);
Route::get('/program-notifications/scheduled', [ProgramNotificationController::class, 'scheduled']);
Route::post('/program-notifications/mark-all-read', [ProgramNotificationController::class, 'markAllAsRead']);
Route::post('/program-notifications/{notification}/mark-read', [ProgramNotificationController::class, 'markAsRead']);
Route::apiResource('program-notifications', ProgramNotificationController::class);

// Users Routes (for Program Management)
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);

// Art & Set Properti Routes (using ProductionController)
Route::get('/art-set-properti', [ProductionController::class, 'index']);
Route::post('/art-set-properti', [ProductionController::class, 'store']);
Route::get('/art-set-properti/{equipment}', [ProductionController::class, 'show']);
Route::put('/art-set-properti/{equipment}', [ProductionController::class, 'update']);
Route::delete('/art-set-properti/{equipment}', [ProductionController::class, 'destroy']);
Route::post('/art-set-properti/{equipment}/assign', [ProductionController::class, 'assign']);
Route::post('/art-set-properti/{equipment}/unassign', [ProductionController::class, 'unassign']);
Route::get('/art-set-properti/available', [ProductionController::class, 'getAvailable']);
Route::get('/art-set-properti/needs-maintenance', [ProductionController::class, 'getNeedsMaintenance']);

// Approval Workflow Routes (simplified - using existing controllers)
Route::post('/programs/{program}/submit-approval', [ProgramController::class, 'submitForApproval']);
Route::post('/programs/{program}/approve', [ProgramController::class, 'approve']);
Route::post('/programs/{program}/reject', [ProgramController::class, 'reject']);
Route::post('/episodes/{episode}/submit-rundown', [EpisodeController::class, 'submitRundownForApproval']);
Route::post('/episodes/{episode}/approve-rundown', [EpisodeController::class, 'approveRundown']);
Route::post('/episodes/{episode}/reject-rundown', [EpisodeController::class, 'rejectRundown']);
Route::post('/schedules/{schedule}/submit-approval', [ScheduleController::class, 'submitForApproval']);
Route::post('/schedules/{schedule}/approve', [ScheduleController::class, 'approve']);
Route::post('/schedules/{schedule}/reject', [ScheduleController::class, 'reject']);
Route::get('/approvals/pending', [ProgramController::class, 'getPendingApprovals']);
Route::get('/approvals/history', [ProgramController::class, 'getApprovalHistory']);

// Analytics Routes (simplified - using existing controllers)
Route::get('/programs/{program}/analytics', [ProgramController::class, 'getAnalytics']);
Route::get('/programs/{program}/performance-metrics', [ProgramController::class, 'getPerformanceMetrics']);
Route::get('/programs/{program}/kpi-summary', [ProgramController::class, 'getKPISummary']);
Route::get('/programs/{program}/team-performance', [ProgramController::class, 'getTeamPerformance']);
Route::get('/programs/{program}/content-analytics', [ProgramController::class, 'getContentAnalytics']);
Route::get('/programs/{program}/trends', [ProgramController::class, 'getTrends']);
Route::get('/programs/{program}/views-tracking', [ProgramController::class, 'getViewsTracking']);
Route::get('/analytics/dashboard', [ProgramController::class, 'getDashboardAnalytics']);
Route::get('/analytics/comparative', [ProgramController::class, 'getComparativeAnalytics']);
Route::get('/programs/{program}/analytics/export', [ProgramController::class, 'exportAnalytics']);

// Export Routes (simplified - using existing controllers)
Route::get('/episodes/{episode}/export/word', [EpisodeController::class, 'exportScriptToWord']);
Route::get('/episodes/{episode}/export/powerpoint', [EpisodeController::class, 'exportScriptToPowerPoint']);
Route::get('/episodes/{episode}/export/pdf', [EpisodeController::class, 'exportScriptToPDF']);
Route::get('/programs/{program}/export/data', [ProgramController::class, 'exportProgramData']);
Route::get('/schedules/{schedule}/export/data', [ScheduleController::class, 'exportScheduleData']);
Route::get('/programs/{program}/export/media', [ProgramController::class, 'exportMediaFiles']);
Route::post('/episodes/bulk-export', [EpisodeController::class, 'bulkExportEpisodes']);

// Reminder & Notification Routes (Extended)
Route::get('/notifications', [ReminderNotificationController::class, 'index']);
Route::get('/notifications/unread-count', [ReminderNotificationController::class, 'getUnreadCount']);
Route::post('/notifications/{notification}/mark-read', [ReminderNotificationController::class, 'markAsRead']);
Route::post('/notifications/mark-all-read', [ReminderNotificationController::class, 'markAllAsRead']);
Route::post('/notifications/reminder', [ReminderNotificationController::class, 'createReminder']);
Route::get('/notifications/upcoming', [ReminderNotificationController::class, 'getUpcomingReminders']);
Route::get('/notifications/deadlines', [ReminderNotificationController::class, 'getDeadlineReminders']);
Route::get('/notifications/overdue', [ReminderNotificationController::class, 'getOverdueAlerts']);
Route::get('/notifications/preferences', [ReminderNotificationController::class, 'getNotificationPreferences']);
Route::post('/notifications/preferences', [ReminderNotificationController::class, 'updateNotificationPreferences']);
Route::delete('/notifications/{notification}', [ReminderNotificationController::class, 'destroy']);
Route::post('/notifications/bulk-delete', [ReminderNotificationController::class, 'bulkDelete']);

// Workflow Automation Routes (Cron Jobs)
Route::post('/workflow/send-reminders', [ReminderNotificationController::class, 'sendReminderNotifications']);
Route::post('/workflow/update-episode-statuses', [ReminderNotificationController::class, 'updateEpisodeStatuses']);
Route::post('/workflow/auto-close-programs', [ReminderNotificationController::class, 'autoCloseInactivePrograms']);
Route::post('/workflow/set-deadlines/{program}', [ReminderNotificationController::class, 'setAutomaticDeadlines']);

// ===== NEW PROGRAM WORKFLOW SYSTEM ROUTES =====

// Team Management Routes
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamManagementController::class, 'index']);
    Route::post('/', [TeamManagementController::class, 'store']);
    Route::get('/{id}', [TeamManagementController::class, 'show']);
    Route::put('/{id}', [TeamManagementController::class, 'update']);
    Route::post('/{id}/members', [TeamManagementController::class, 'addMember']);
    Route::delete('/{id}/members', [TeamManagementController::class, 'removeMember']);
    Route::put('/{id}/members/role', [TeamManagementController::class, 'updateMemberRole']);
    Route::get('/by-role', [TeamManagementController::class, 'getTeamsByRole']);
    Route::get('/department/{department}', [TeamManagementController::class, 'getTeamsByDepartment']);
    Route::get('/user/my-teams', [TeamManagementController::class, 'getUserTeams']);
});

// Workflow State Machine Routes
Route::prefix('workflow')->group(function () {
    Route::get('/{entityType}/{entityId}/transitions', [WorkflowController::class, 'getAvailableTransitions']);
    Route::post('/{entityType}/{entityId}/execute', [WorkflowController::class, 'executeTransition']);
    Route::get('/{entityType}/{entityId}/status', [WorkflowController::class, 'getWorkflowStatus']);
    Route::get('/steps', [WorkflowController::class, 'getWorkflowSteps']);
    Route::get('/states', [WorkflowController::class, 'getWorkflowStates']);
    Route::get('/dashboard', [WorkflowController::class, 'getWorkflowDashboard']);
});

// File Management Routes
Route::prefix('files')->group(function () {
    Route::post('/upload', [FileManagementController::class, 'uploadFile']);
    Route::post('/bulk-upload', [FileManagementController::class, 'bulkUpload']);
    Route::get('/statistics', [FileManagementController::class, 'getFileStatistics']);
    Route::get('/{entityType}/{entityId}', [FileManagementController::class, 'getFiles']);
    Route::get('/{id}/download', [FileManagementController::class, 'downloadFile']);
    Route::put('/{id}', [FileManagementController::class, 'updateFile']);
    Route::delete('/{id}', [FileManagementController::class, 'deleteFile']);
    Route::get('/{entityType}/{entityId}/statistics', [FileManagementController::class, 'getFileStatistics']);
});

// Enhanced Notification Routes
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    Route::get('/statistics', [NotificationController::class, 'getStatistics']);
    Route::get('/workflow', [NotificationController::class, 'getWorkflowNotifications']);
    Route::post('/test', [NotificationController::class, 'sendTestNotification']);
    Route::get('/preferences', [NotificationController::class, 'getPreferences']);
    Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
});

// Enhanced Approval Workflow Routes
Route::prefix('approvals')->group(function () {
    Route::post('/programs/{id}/submit', [ApprovalWorkflowController::class, 'submitProgramForApproval']);
    Route::post('/programs/{id}/approve', [ApprovalWorkflowController::class, 'approveProgram']);
    Route::post('/programs/{id}/reject', [ApprovalWorkflowController::class, 'rejectProgram']);
    Route::post('/rundowns/{id}/submit', [ApprovalWorkflowController::class, 'submitRundownForApproval']);
    Route::post('/rundowns/{id}/approve', [ApprovalWorkflowController::class, 'approveRundown']);
    Route::post('/rundowns/{id}/reject', [ApprovalWorkflowController::class, 'rejectRundown']);
    Route::post('/schedules/{id}/submit', [ApprovalWorkflowController::class, 'submitScheduleForApproval']);
    Route::post('/schedules/{id}/approve', [ApprovalWorkflowController::class, 'approveSchedule']);
    Route::post('/schedules/{id}/reject', [ApprovalWorkflowController::class, 'rejectSchedule']);
    Route::get('/pending', [ApprovalWorkflowController::class, 'getPendingApprovals']);
    Route::get('/history', [ApprovalWorkflowController::class, 'getApprovalHistory']);
});

// Art & Set Properti Routes
Route::prefix('art-set-properti')->group(function () {
    Route::get('/', [ArtSetPropertiController::class, 'index']);
    Route::post('/', [ArtSetPropertiController::class, 'store']);
    Route::get('/{id}', [ArtSetPropertiController::class, 'show']);
    Route::put('/{id}', [ArtSetPropertiController::class, 'update']);
    Route::delete('/{id}', [ArtSetPropertiController::class, 'destroy']);
    Route::post('/{id}/approve', [ArtSetPropertiController::class, 'approveRequest']);
    Route::post('/{id}/reject', [ArtSetPropertiController::class, 'rejectRequest']);
    Route::post('/{id}/assign', [ArtSetPropertiController::class, 'assignEquipment']);
    Route::post('/{id}/return', [ArtSetPropertiController::class, 'returnEquipment']);
    Route::get('/inventory/summary', [ArtSetPropertiController::class, 'getInventory']);
});

// Program Analytics Routes
Route::prefix('analytics')->group(function () {
    Route::get('/programs/{id}', [ProgramAnalyticsController::class, 'getProgramAnalytics']);
    Route::get('/programs/{id}/performance', [ProgramAnalyticsController::class, 'getPerformanceMetrics']);
    Route::get('/programs/{id}/kpi', [ProgramAnalyticsController::class, 'getKPISummary']);
    Route::get('/programs/{id}/team-performance', [ProgramAnalyticsController::class, 'getTeamPerformance']);
    Route::get('/programs/{id}/content', [ProgramAnalyticsController::class, 'getContentAnalytics']);
    Route::get('/programs/{id}/trends', [ProgramAnalyticsController::class, 'getTrends']);
    Route::get('/programs/{id}/views', [ProgramAnalyticsController::class, 'getViewsTracking']);
    Route::get('/dashboard', [ProgramAnalyticsController::class, 'getDashboardAnalytics']);
    Route::get('/comparative', [ProgramAnalyticsController::class, 'getComparativeAnalytics']);
    Route::get('/programs/{id}/export', [ProgramAnalyticsController::class, 'exportAnalytics']);
});

// ===== NEW PROGRAM REGULAR MANAGEMENT SYSTEM (2025) =====
use App\Http\Controllers\ProductionTeamController;
use App\Http\Controllers\ProgramRegularController;
use App\Http\Controllers\ProgramEpisodeController;
use App\Http\Controllers\ProgramProposalController;
use App\Http\Controllers\ProgramApprovalController;

// Production Teams Routes (Independen dari Program)
Route::prefix('production-teams')->group(function () {
    Route::get('/', [ProductionTeamController::class, 'index']);
    Route::post('/', [ProductionTeamController::class, 'store']);
    Route::get('/producers', [ProductionTeamController::class, 'getProducers']);
    Route::get('/{id}', [ProductionTeamController::class, 'show']);
    Route::put('/{id}', [ProductionTeamController::class, 'update']);
    Route::delete('/{id}', [ProductionTeamController::class, 'destroy']);
    Route::post('/{id}/members', [ProductionTeamController::class, 'addMembers']);
    Route::delete('/{id}/members', [ProductionTeamController::class, 'removeMembers']);
    Route::get('/{id}/available-users', [ProductionTeamController::class, 'getAvailableUsers']);
});

// Program Regular Routes (Program Mingguan 53 Episode)
Route::prefix('program-regular')->group(function () {
    Route::get('/', [ProgramRegularController::class, 'index']);
    Route::post('/', [ProgramRegularController::class, 'store']);
    Route::get('/available-teams', [ProgramRegularController::class, 'getAvailableTeams']);
    Route::get('/{id}', [ProgramRegularController::class, 'show']);
    Route::put('/{id}', [ProgramRegularController::class, 'update']);
    Route::delete('/{id}', [ProgramRegularController::class, 'destroy']);
    Route::get('/{id}/dashboard', [ProgramRegularController::class, 'dashboard']);
    
    // Program Workflow
    Route::post('/{id}/submit-approval', [ProgramRegularController::class, 'submitForApproval']);
    Route::post('/{id}/approve', [ProgramRegularController::class, 'approve']);
    Route::post('/{id}/reject', [ProgramRegularController::class, 'reject']);
});

// Program Episodes Routes (53 Episode per Program)
Route::prefix('program-episodes')->group(function () {
    Route::get('/', [ProgramEpisodeController::class, 'index']);
    Route::get('/upcoming', [ProgramEpisodeController::class, 'getUpcoming']);
    Route::get('/{id}', [ProgramEpisodeController::class, 'show']);
    Route::put('/{id}', [ProgramEpisodeController::class, 'update']);
    Route::patch('/{id}/status', [ProgramEpisodeController::class, 'updateStatus']);
    
    // Episode Deadlines
    Route::get('/{id}/deadlines', [ProgramEpisodeController::class, 'getDeadlines']);
    Route::post('/{episodeId}/deadlines/{deadlineId}/complete', [ProgramEpisodeController::class, 'completeDeadline']);
    
    // Episode Rundown Approval
    Route::post('/{id}/submit-rundown', [ProgramEpisodeController::class, 'submitRundown']);
});

// Program Proposals Routes (Google Spreadsheet Integration)
Route::prefix('program-proposals')->group(function () {
    Route::get('/', [ProgramProposalController::class, 'index']);
    Route::post('/', [ProgramProposalController::class, 'store']);
    Route::get('/{id}', [ProgramProposalController::class, 'show']);
    Route::put('/{id}', [ProgramProposalController::class, 'update']);
    Route::delete('/{id}', [ProgramProposalController::class, 'destroy']);
    
    // Spreadsheet Sync
    Route::post('/{id}/sync', [ProgramProposalController::class, 'syncFromSpreadsheet']);
    Route::get('/{id}/embedded-view', [ProgramProposalController::class, 'getEmbeddedView']);
    
    // Proposal Workflow
    Route::post('/{id}/submit', [ProgramProposalController::class, 'submitForReview']);
    Route::post('/{id}/review', [ProgramProposalController::class, 'markAsUnderReview']);
    Route::post('/{id}/approve', [ProgramProposalController::class, 'approve']);
    Route::post('/{id}/reject', [ProgramProposalController::class, 'reject']);
    Route::post('/{id}/request-revision', [ProgramProposalController::class, 'requestRevision']);
});

// Program Approvals Routes (Unified Approval System)
Route::prefix('program-approvals')->group(function () {
    Route::get('/', [ProgramApprovalController::class, 'index']);
    Route::post('/', [ProgramApprovalController::class, 'store']);
    Route::get('/pending', [ProgramApprovalController::class, 'getPending']);
    Route::get('/overdue', [ProgramApprovalController::class, 'getOverdue']);
    Route::get('/urgent', [ProgramApprovalController::class, 'getUrgent']);
    Route::get('/history', [ProgramApprovalController::class, 'getHistory']);
    Route::get('/{id}', [ProgramApprovalController::class, 'show']);
    Route::put('/{id}', [ProgramApprovalController::class, 'update']);
    
    // Approval Actions
    Route::post('/{id}/review', [ProgramApprovalController::class, 'markAsReviewed']);
    Route::post('/{id}/approve', [ProgramApprovalController::class, 'approve']);
    Route::post('/{id}/reject', [ProgramApprovalController::class, 'reject']);
    Route::post('/{id}/cancel', [ProgramApprovalController::class, 'cancel']);
});

