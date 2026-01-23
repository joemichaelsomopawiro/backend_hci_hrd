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
use App\Http\Controllers\Api\PrManagerProgramController;
use App\Http\Controllers\Api\PrProducerController;
use App\Http\Controllers\Api\PrManagerDistribusiController;
use App\Http\Controllers\Api\PrRevisionController;
use App\Http\Controllers\Api\PrProgramCrewController;
use App\Http\Controllers\Api\PrRoleFilterController;
use App\Http\Controllers\Api\TaskVisibilityController;
use App\Http\Controllers\Api\TaskReassignmentController;

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
Route::get('/employees/roles', [EmployeeController::class, 'getRoles']); // Endpoint untuk get roles
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
    Route::middleware('auth:sanctum')->group(function () {
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

// Auth routes - dengan rate limiting untuk prevent brute force
Route::prefix('auth')->group(function () {
    Route::post('/send-register-otp', [AuthController::class, 'sendRegisterOtp'])->middleware('throttle:auth');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:auth');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/send-forgot-password-otp', [AuthController::class, 'sendForgotPasswordOtp'])->middleware('throttle:auth');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    // Route yang perlu auth (pakai check.token.expiration)
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::get('/check-token', [AuthController::class, 'checkTokenStatus'])->middleware('throttle:60,1'); // Check token status dengan rate limit
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:sensitive'); // Refresh token
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/check-employee-status', [AuthController::class, 'checkEmployeeStatus']);
        Route::post('/upload-profile-picture', [AuthController::class, 'uploadProfilePicture'])->middleware('throttle:uploads');
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
// Public route untuk national holidays (tidak perlu auth)
Route::get('/calendar/national-holidays', [NationalHolidayController::class, 'getNationalHolidays']);

// Routes untuk kalender nasional (perlu auth)
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
// Routes untuk Music Arranger workflow - DISABLED (using new live_tv_api.php)
// Route::prefix('music-arranger')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/dashboard', [MusicArrangerController::class, 'dashboard']);
//     Route::get('/songs', [MusicArrangerController::class, 'getSongs']);
//     Route::get('/singers', [MusicArrangerController::class, 'getSingers']);
//     Route::get('/singers-fixed', [MusicArrangerController::class, 'getSingersFixed']);
//     Route::get('/test-profile-url', [MusicArrangerController::class, 'testProfileUrl']);
//     Route::get('/songs/{id}/audio', [MusicArrangerController::class, 'getSongAudio']);
//     
//     // Add new song and singer
//     Route::post('/songs', [MusicArrangerController::class, 'addSong']);
//     Route::post('/singers', [MusicArrangerController::class, 'addSinger']);
//     
//     // Update and delete songs
//     Route::put('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
//     Route::patch('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
//     Route::delete('/songs/{id}', [MusicArrangerController::class, 'deleteSong']);
//     
//     // Update and delete singers
//     Route::put('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
//     Route::patch('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
//     Route::post('/singers/{id}', [MusicArrangerController::class, 'updateSinger']); // For _method override
//     Route::delete('/singers/{id}', [MusicArrangerController::class, 'deleteSinger']);
//     
//     // Music request routes
//     Route::post('/requests', [MusicArrangerController::class, 'submitRequest']);
//     Route::get('/requests', [MusicArrangerController::class, 'getMyRequests']);
//     Route::get('/requests/{id}', [MusicArrangerController::class, 'getRequestDetail']);
//     Route::put('/requests/{id}', [MusicArrangerController::class, 'updateRequest']);
//     Route::put('/requests/{id}/update', [MusicArrangerController::class, 'updateRequest']); // Alias for frontend compatibility
//     Route::post('/requests/{id}/update', [MusicArrangerController::class, 'updateRequest']); // POST alias for frontend compatibility
//     Route::delete('/requests/{id}', [MusicArrangerController::class, 'cancelRequest']);
//     
//     // Music submission routes
//     Route::get('/submissions', [MusicArrangerController::class, 'getSubmissions']);
//     Route::get('/submissions/{id}', [MusicArrangerController::class, 'getSubmission']);
// });

// ===== PRODUCER MUSIC ROUTES =====
// Routes untuk Producer music workflow - DISABLED (using new live_tv_api.php)
// Route::prefix('producer/music')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/dashboard', [ProducerMusicController::class, 'dashboard']);
//     Route::get('/songs', [ProducerMusicController::class, 'getSongs']); // Get available songs
//     Route::get('/songs/{id}/audio', [ProducerMusicController::class, 'getSongAudio']); // Get song audio
//     Route::get('/requests', [ProducerMusicController::class, 'getAllRequests']); // Add missing route
//     Route::get('/submissions', [ProducerMusicController::class, 'getAllSubmissions']); // Get all submissions with detailed data
//     Route::get('/requests/pending', [ProducerMusicController::class, 'getPendingRequests']);
//     Route::get('/requests/approved', [ProducerMusicController::class, 'getApprovedRequests']);
//     Route::get('/requests/rejected', [ProducerMusicController::class, 'getRejectedRequests']);
//     Route::get('/requests/status/{status}', [ProducerMusicController::class, 'getRequestsByStatus']);
//     Route::get('/requests/my', [ProducerMusicController::class, 'getMyRequests']);
//     Route::get('/requests/{id}', [ProducerMusicController::class, 'getRequestDetail']);
//     Route::get('/singers', [ProducerMusicController::class, 'getSingers']);

//     // Add new song and singer (Producer can also add)
//     Route::post('/songs', [ProducerMusicController::class, 'addSong']);
//     Route::post('/singers', [ProducerMusicController::class, 'addSinger']);

//     // Update and delete songs (Producer CRUD)
//     Route::put('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
//     Route::patch('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
//     Route::delete('/songs/{id}', [ProducerMusicController::class, 'deleteSong']);

//     // Update and delete singers (Producer CRUD)
//     Route::put('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
//     Route::patch('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
//     Route::delete('/singers/{id}', [ProducerMusicController::class, 'deleteSinger']);

//     // Producer actions
//     Route::post('/requests/{id}/take', [ProducerMusicController::class, 'takeRequest']);
//     Route::post('/requests/{id}/modify', [ProducerMusicController::class, 'modifyRequest']);
//     Route::post('/requests/{id}/approve', [ProducerMusicController::class, 'approveRequest']);
//     Route::post('/requests/{id}/reject', [ProducerMusicController::class, 'rejectRequest']);
// });

// ===== TEST ROUTES =====
// DISABLED - Test routes causing controller issues
// Route::get('/test/singers', [App\Http\Controllers\TestController::class, 'testSingers']);
// Route::get('/test/cors', [App\Http\Controllers\TestController::class, 'testCors']);
// Route::get('/test/producer-modify-workflow', [App\Http\Controllers\TestController::class, 'testProducerModifyWorkflow']);
// Route::get('/test/database-schema', [App\Http\Controllers\TestController::class, 'testDatabaseSchema']);
// Route::get('/test/validation-fix', [App\Http\Controllers\TestController::class, 'testValidationFix']);
// Route::get('/test/database-column-fix', [App\Http\Controllers\TestController::class, 'testDatabaseColumnFix']);

// ===== MUSIC NOTIFICATION ROUTES =====
// Routes untuk notifikasi music workflow - DISABLED (using new live_tv_api.php)
// Route::prefix('music/notifications')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/', [MusicNotificationController::class, 'index']);
//     Route::get('/unread-count', [MusicNotificationController::class, 'unreadCount']);
//     Route::get('/count', [MusicNotificationController::class, 'unreadCount']); // Alias for frontend compatibility
//     Route::put('/{id}/read', [MusicNotificationController::class, 'markAsRead']);
//     Route::put('/mark-all-read', [MusicNotificationController::class, 'markAllAsRead']);
// });

// ===== GENERAL NOTIFICATION ROUTES =====
// Routes untuk notifikasi umum (compatibility dengan frontend) - DISABLED
// Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/count', [MusicNotificationController::class, 'unreadCount']);
//     Route::get('/read-status/{id}', [MusicNotificationController::class, 'getReadStatus']);
//     Route::put('/{id}/read', [MusicNotificationController::class, 'markAsRead']);
//     Route::put('/mark-read', [MusicNotificationController::class, 'markAsReadWithoutId']); // Alias for frontend compatibility
//     Route::get('/', [MusicNotificationController::class, 'index']);
// });

// ===== AUDIO ROUTES =====
// Routes untuk audio playback dan upload - DISABLED
// Route::prefix('audio')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/{songId}', [AudioController::class, 'stream']);
//     Route::get('/{songId}/info', [AudioController::class, 'info']);
//     Route::post('/{songId}/upload', [AudioController::class, 'upload']);
//     Route::delete('/{songId}', [AudioController::class, 'delete']);
// });

// ===== MUSIC WORKFLOW ROUTES =====
// Routes untuk music workflow system - DISABLED (using new live_tv_api.php)
// Route::prefix('music-workflow')->middleware(['auth:sanctum'])->group(function () {
//     // General workflow routes
//     Route::get('/current-submission', [MusicWorkflowController::class, 'getCurrentSubmission']);
//     Route::get('/list', [MusicWorkflowController::class, 'getWorkflowList']);
//     Route::get('/submissions/{id}/history', [MusicWorkflowController::class, 'getWorkflowHistory']);
//     Route::post('/submissions/{id}/transition', [MusicWorkflowController::class, 'transitionState']);
//     Route::post('/submissions', [MusicWorkflowController::class, 'createSubmission']);
//     Route::put('/submissions/{id}', [MusicWorkflowController::class, 'update']);
//     Route::patch('/submissions/{id}', [MusicWorkflowController::class, 'update']);
//     Route::delete('/submissions/{id}', [MusicWorkflowController::class, 'destroy']);
//     
//     // Notification routes
//     Route::get('/notifications', [MusicWorkflowController::class, 'getNotifications']);
//     Route::post('/notifications/{id}/read', [MusicWorkflowController::class, 'markNotificationAsRead']);
//     Route::post('/notifications/mark-all-read', [MusicWorkflowController::class, 'markAllNotificationsAsRead']);
//     
//     // Analytics routes
//     Route::get('/stats', [MusicWorkflowController::class, 'getWorkflowStats']);
//     Route::get('/analytics', [MusicWorkflowController::class, 'getAnalytics']);
//     
//     // Music Arranger routes
//     Route::post('/music-arranger/workflow/{id}/arrange', [MusicWorkflowController::class, 'submitArrangement']);
//     Route::post('/music-arranger/workflow/{id}/resubmit-arrangement', [MusicWorkflowController::class, 'resubmitArrangement']);
//     
//     // Producer routes
//     Route::post('/producer/workflow/{id}/approve', [MusicWorkflowController::class, 'approveSubmission']);
//     Route::post('/producer/workflow/{id}/reject', [MusicWorkflowController::class, 'rejectSubmission']);
//     Route::post('/producer/workflow/{id}/process', [MusicWorkflowController::class, 'processArrangement']);
//     Route::post('/producer/workflow/{id}/qc-music', [MusicWorkflowController::class, 'qcMusic']);
//     Route::post('/producer/workflow/{id}/approve-quality', [MusicWorkflowController::class, 'approveQuality']);
//     Route::post('/producer/workflow/{id}/final-approve', [MusicWorkflowController::class, 'finalApprove']);
//     
//     // Sound Engineer routes
//     Route::post('/sound-engineer/workflow/{id}/accept', [MusicWorkflowController::class, 'acceptSoundEngineeringWork']);
//     Route::post('/sound-engineer/workflow/{id}/complete', [MusicWorkflowController::class, 'completeSoundEngineering']);
//     Route::post('/sound-engineer/workflow/{id}/reject-to-arranger', [MusicWorkflowController::class, 'rejectArrangementBackToArranger']);
//     
//     // Creative routes
//     Route::post('/creative/workflow/{id}/accept', [MusicWorkflowController::class, 'acceptCreativeWork']);
//     Route::post('/creative/workflow/{id}/submit-work', [MusicWorkflowController::class, 'submitCreativeWork']);
// });

// Music Arranger History Routes - DISABLED (using new live_tv_api.php)
// Route::prefix('music-arranger-history')->middleware(['auth:sanctum'])->group(function () {
//     Route::get('/submissions', [MusicArrangerHistoryController::class, 'getSubmissions']);
//     Route::get('/submissions/{id}', [MusicArrangerHistoryController::class, 'getSubmission']);
//     Route::put('/submissions/{id}', [MusicArrangerHistoryController::class, 'updateSubmission']);
//     Route::delete('/submissions/{id}', [MusicArrangerHistoryController::class, 'deleteSubmission']);
//     Route::post('/submissions/{id}/submit', [MusicArrangerHistoryController::class, 'submitSubmission']);
//     Route::post('/submissions/{id}/cancel', [MusicArrangerHistoryController::class, 'cancelSubmission']);
//     Route::post('/submissions/{id}/resubmit', [MusicArrangerHistoryController::class, 'resubmitSubmission']);
//     Route::get('/submissions/{id}/download', [MusicArrangerHistoryController::class, 'downloadFiles']);
// });

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

// // ===== MUSIC PROGRAM ROUTES =====
// // Load Music System API routes
// Route::prefix('music')->group(function () {
//     require __DIR__.'/music_api.php';
// });

// // Include Live TV Program API Routes
// require __DIR__.'/live_tv_api.php';

// ===== PROGRAM REGULAR ROUTES =====
Route::prefix('program-regular')->middleware(['auth:sanctum'])->group(function () {

    // Role Filtering Routes (untuk semua role dengan hierarki)
    Route::get('/accessible-roles', [PrRoleFilterController::class, 'getAccessibleRoles']); // Get list role yang bisa di-filter
    Route::get('/validate-role-access/{targetRole}', [PrRoleFilterController::class, 'validateRoleAccess']); // Validate akses ke role tertentu

    // Manager Program Routes
    Route::prefix('manager-program')->group(function () {
        Route::post('/programs', [PrManagerProgramController::class, 'createProgram']); // Create program (hanya Manager Program)
        Route::get('/programs', [PrManagerProgramController::class, 'listPrograms']); // List semua program (semua bisa lihat)
        Route::get('/programs/{id}', [PrManagerProgramController::class, 'showProgram']); // Detail program
        Route::put('/programs/{id}', [PrManagerProgramController::class, 'updateProgram']); // Update program
        Route::delete('/programs/{id}', [PrManagerProgramController::class, 'deleteProgram']); // Delete program
        Route::post('/programs/{id}/concepts', [PrManagerProgramController::class, 'createConcept']); // Create konsep
        Route::put('/programs/{id}/concepts/{conceptId}', [PrManagerProgramController::class, 'updateConcept']); // Update konsep
        Route::delete('/programs/{id}/concepts/{conceptId}', [PrManagerProgramController::class, 'deleteConcept']); // Delete konsep
        Route::post('/programs/{id}/approve', [PrManagerProgramController::class, 'approveProgram']); // Approve program dari Producer
        Route::post('/programs/{id}/reject', [PrManagerProgramController::class, 'rejectProgram']); // Reject program dari Producer
        Route::post('/programs/{id}/submit-to-distribusi', [PrManagerProgramController::class, 'submitToDistribusi']); // Submit ke Manager Distribusi
        Route::get('/programs/{id}/schedules', [PrManagerProgramController::class, 'viewSchedules']); // View jadwal
        Route::get('/programs/{id}/distribution-reports', [PrManagerProgramController::class, 'viewDistributionReports']); // View laporan distribusi
        Route::get('/programs/{id}/revision-history', [PrManagerProgramController::class, 'viewRevisionHistory']); // View revision history
        Route::put('/episodes/{id}', [PrManagerProgramController::class, 'updateEpisode']); // Update episode
        Route::delete('/episodes/{id}', [PrManagerProgramController::class, 'deleteEpisode']); // Delete episode

        // Team Members Management
        Route::get('/programs/{id}/team-members', [PrProgramCrewController::class, 'index']); // List team
        Route::post('/programs/{id}/team-members', [PrProgramCrewController::class, 'store']); // Add team member
        Route::delete('/programs/{id}/team-members/{memberId}', [PrProgramCrewController::class, 'destroy']); // Remove team member
    });

    // Producer Routes
    Route::prefix('producer')->group(function () {
        Route::get('/concepts', [PrProducerController::class, 'listConceptsForApproval']); // List konsep untuk approval
        Route::post('/concepts/{id}/approve', [PrProducerController::class, 'approveConcept']); // Approve konsep (DEPRECATED)
        Route::post('/concepts/{id}/reject', [PrProducerController::class, 'rejectConcept']); // Reject konsep (DEPRECATED)
        Route::post('/concepts/{id}/mark-as-read', [PrProducerController::class, 'markConceptAsRead']); // Mark as read (NEW)
        Route::post('/programs/{id}/production-schedules', [PrProducerController::class, 'createProductionSchedule']); // Create jadwal produksi
        Route::put('/production-schedules/{id}', [PrProducerController::class, 'updateProductionSchedule']); // Update jadwal produksi
        Route::delete('/production-schedules/{id}', [PrProducerController::class, 'deleteProductionSchedule']); // Delete jadwal produksi
        Route::put('/episodes/{id}/status', [PrProducerController::class, 'updateEpisodeStatus']); // Update status episode
        Route::put('/episodes/{id}', [PrProducerController::class, 'updateEpisode']); // Update episode
        Route::delete('/episodes/{id}', [PrProducerController::class, 'deleteEpisode']); // Delete episode
        Route::post('/episodes/{id}/files', [PrProducerController::class, 'uploadFile']); // Upload file setelah editing
        Route::post('/programs/{id}/submit-to-manager', [PrProducerController::class, 'submitToManager']); // Submit ke Manager Program
        Route::get('/programs/{id}/distribution-schedules', [PrProducerController::class, 'viewDistributionSchedules']); // View jadwal tayang
        Route::get('/programs/{id}/distribution-reports', [PrProducerController::class, 'viewDistributionReports']); // View laporan distribusi
        Route::get('/programs/{id}/revision-history', [PrProducerController::class, 'viewRevisionHistory']); // View revision history
    });

    // Manager Distribusi Routes
    Route::prefix('distribusi')->group(function () {
        Route::get('/programs', [PrManagerDistribusiController::class, 'listProgramsForDistribusi']); // List program untuk distribusi
        Route::post('/programs/{id}/verify', [PrManagerDistribusiController::class, 'verifyProgram']); // Verify program
        Route::get('/programs/{id}/concept', [PrManagerDistribusiController::class, 'viewProgramConcept']); // View konsep program
        Route::get('/programs/{id}/production-schedules', [PrManagerDistribusiController::class, 'viewProductionSchedules']); // View jadwal produksi
        Route::get('/episodes/{id}/shooting-schedule', [PrManagerDistribusiController::class, 'viewShootingSchedule']); // View jadwal syuting per episode
        Route::get('/programs/{id}/files', [PrManagerDistribusiController::class, 'viewProgramFiles']); // View file program
        Route::post('/programs/{id}/distribution-schedules', [PrManagerDistribusiController::class, 'createDistributionSchedule']); // Create jadwal tayang
        Route::put('/distribution-schedules/{id}', [PrManagerDistribusiController::class, 'updateDistributionSchedule']); // Update jadwal tayang
        Route::delete('/distribution-schedules/{id}', [PrManagerDistribusiController::class, 'deleteDistributionSchedule']); // Delete jadwal tayang
        Route::post('/episodes/{id}/mark-aired', [PrManagerDistribusiController::class, 'markAsAired']); // Mark episode as aired
        Route::post('/programs/{id}/distribution-reports', [PrManagerDistribusiController::class, 'createDistributionReport']); // Create laporan distribusi
        Route::get('/distribution-reports', [PrManagerDistribusiController::class, 'listDistributionReports']); // List laporan distribusi
        Route::put('/distribution-reports/{id}', [PrManagerDistribusiController::class, 'updateDistributionReport']); // Update laporan distribusi
        Route::delete('/distribution-reports/{id}', [PrManagerDistribusiController::class, 'deleteDistributionReport']); // Delete laporan distribusi
        Route::get('/programs/{id}/revision-history', [PrManagerDistribusiController::class, 'viewRevisionHistory']); // View revision history
    });

    // Revision Routes (semua role bisa request, hanya Manager Program yang bisa approve/reject)
    Route::prefix('revisions')->group(function () {
        Route::post('/programs/{id}/request', [PrRevisionController::class, 'requestRevision']); // Request revisi
        Route::get('/programs/{id}/history', [PrRevisionController::class, 'getRevisionHistory']); // History revisi
        Route::post('/{id}/approve', [PrRevisionController::class, 'approveRevision']); // Approve revisi (hanya Manager Program)
        Route::post('/{id}/reject', [PrRevisionController::class, 'rejectRevision']); // Reject revisi (hanya Manager Program)
    });
});

// ===== TASK VISIBILITY & REASSIGNMENT ROUTES =====
Route::prefix('tasks')->middleware(['auth:sanctum'])->group(function () {
    // Task Visibility Routes (All roles can access)
    Route::get('/all', [\App\Http\Controllers\Api\TaskVisibilityController::class, 'getAllTasks']); // Get all tasks with filters
    Route::get('/statistics', [\App\Http\Controllers\Api\TaskVisibilityController::class, 'getTaskStatistics']); // Get task statistics
    Route::get('/{taskType}/{taskId}', [\App\Http\Controllers\Api\TaskVisibilityController::class, 'getTaskDetail']); // Get task detail

    // Task Reassignment Routes (Manager Program and Producer only)
    Route::post('/reassign', [\App\Http\Controllers\Api\TaskReassignmentController::class, 'reassignTask']); // Reassign task
    Route::get('/{taskType}/{taskId}/reassignment-history', [\App\Http\Controllers\Api\TaskReassignmentController::class, 'getReassignmentHistory']); // Get reassignment history
    Route::get('/available-users', [\App\Http\Controllers\Api\TaskReassignmentController::class, 'getAvailableUsers']); // Get available users for dropdown
});
