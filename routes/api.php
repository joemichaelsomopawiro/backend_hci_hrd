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
use App\Http\Controllers\WorshipAttendanceController;
use App\Http\Controllers\MorningReflectionController;

use App\Http\Controllers\MorningReflectionAttendanceController;

use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\NationalHolidayController;
use App\Http\Controllers\CustomRoleController;
use App\Http\Controllers\GaDashboardController;
use App\Http\Controllers\ZoomLinkController;


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

    // ===== ABSENSI RENUNGAN PAGI - ROUTES BARU =====
    Route::middleware(['auth:sanctum', 'role:ga'])->group(function () {
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
    Route::post('/', [LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::delete('/{id}', [LeaveRequestController::class, 'destroy']);
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
    
    // Leave integration routes
    Route::post('/sync-leave', [AttendanceController::class, 'syncLeaveToAttendance']);
    Route::post('/sync-leave-date-range', [AttendanceController::class, 'syncLeaveToAttendanceDateRange']);
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

// Worship Attendance Routes - GA (General Affairs)
Route::prefix('ga')->middleware(['auth:sanctum', 'role:ga'])->group(function () {
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
    
    // Status kehadiran user
    Route::get('/attendance', [MorningReflectionController::class, 'getAttendance']);
    Route::get('/attendance-user', [MorningReflectionController::class, 'attendance']);
    Route::get('/attendance/{userId}/{date}', [MorningReflectionController::class, 'getUserAttendance']);
    Route::get('/attendance-by-date/{userId}/{date}', [MorningReflectionController::class, 'attendanceByDate']);
    
    // Kehadiran mingguan user
    Route::get('/weekly-attendance/{userId}', [MorningReflectionController::class, 'getWeeklyAttendance']);
    Route::get('/weekly-attendance-user/{userId}', [MorningReflectionController::class, 'weeklyAttendance']);
    
    // Konfigurasi renungan pagi
    Route::get('/config', [MorningReflectionController::class, 'getConfig']);
    Route::get('/config-user', [MorningReflectionController::class, 'config']);
    
    // Statistik kehadiran
    Route::get('/statistics', [MorningReflectionController::class, 'statistics']);
});

// Routes untuk GA (General Affairs) - dengan role middleware
Route::prefix('morning-reflection')->middleware(['auth:sanctum', 'role:General Affairs'])->group(function () {
    // Dashboard kehadiran hari ini
    Route::get('/today-attendance', [MorningReflectionController::class, 'getTodayAttendance']);
    Route::get('/today-attendance-admin', [MorningReflectionController::class, 'todayAttendance']);
    
    // Update konfigurasi (admin only)
    Route::put('/config', [MorningReflectionController::class, 'updateConfig']);
    Route::put('/config-admin', [MorningReflectionController::class, 'updateConfigAdmin']);
});

// Custom Role Management Routes
Route::prefix('custom-roles')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CustomRoleController::class, 'index']);
    Route::post('/', [CustomRoleController::class, 'store']);
    Route::get('/all-roles', [CustomRoleController::class, 'getAllRoles']);
    Route::get('/{id}', [CustomRoleController::class, 'show']);
    Route::put('/{id}', [CustomRoleController::class, 'update']);
    Route::delete('/{id}', [CustomRoleController::class, 'destroy']);
});

// Tambahkan route baru untuk endpoint /api/morning-reflection-attendance/attendance
Route::prefix('morning-reflection-attendance')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/attendance', [\App\Http\Controllers\MorningReflectionAttendanceController::class, 'getAttendance']);
    Route::post('/attend', [\App\Http\Controllers\MorningReflectionAttendanceController::class, 'attend']);
    Route::get('/history/{employeeId}', [\App\Http\Controllers\MorningReflectionAttendanceController::class, 'getHistory']);
    // Tambahkan endpoint lain sesuai kebutuhan frontend
});

// Route testing tanpa rate limit (untuk development)
Route::prefix('test')->group(function () {
    Route::post('/morning-reflection-attendance/attend', [\App\Http\Controllers\MorningReflectionAttendanceController::class, 'attend']);
    Route::get('/morning-reflection-attendance/attendance', [\App\Http\Controllers\MorningReflectionAttendanceController::class, 'getAttendance']);
});

// ===== PERSONAL PROFILE ROUTES =====
// Routes untuk profile pribadi (tanpa autentikasi)
Route::prefix('personal')->group(function () {
    // Profile pribadi
    Route::get('/profile', [\App\Http\Controllers\PersonalProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\PersonalProfileController::class, 'update']);
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
// Routes untuk GA Dashboard - Menampilkan SEMUA data tanpa batasan role
Route::prefix('ga-dashboard')->middleware(['auth:sanctum'])->group(function () {
    // Worship attendance routes
    Route::get('/worship-attendance', [GaDashboardController::class, 'getAllWorshipAttendance']);
    Route::get('/worship-statistics', [GaDashboardController::class, 'getWorshipStatistics']);
    
    // Export routes
    Route::get('/export-worship-attendance', [GaDashboardController::class, 'exportWorshipAttendance']);
    Route::get('/export-leave-requests', [GaDashboardController::class, 'exportLeaveRequests']);
    
    // Leave requests routes
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
    
    // Routes khusus HR untuk manage hari libur
    Route::middleware(['role:HR'])->group(function () {
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
});

// Route publik untuk mengambil link Zoom
Route::get('/zoom-link', [ZoomLinkController::class, 'getZoomLink']);

// Route proteksi untuk update link Zoom (hanya untuk GA dan role terkait)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/ga/zoom-link', [ZoomLinkController::class, 'getZoomLink']);
    Route::post('/ga/zoom-link', [ZoomLinkController::class, 'updateZoomLink']);
});