<?php
/**
 * Script untuk verifikasi tidak ada data duplikasi
 * Mengecek apakah ada data yang sama di database
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceLog;
use App\Models\Attendance;
use Carbon\Carbon;

echo "🔍 VERIFIKASI DATA DUPLIKASI\n";
echo "============================\n\n";

// Get current month info
$currentDate = Carbon::now();
$currentYear = $currentDate->year;
$currentMonth = $currentDate->month;
$monthName = $currentDate->format('F');

echo "📅 Target: {$monthName} {$currentYear}\n";
echo "📊 Rentang: " . $currentDate->startOfMonth()->format('Y-m-d') . " sampai " . $currentDate->endOfMonth()->format('Y-m-d') . "\n\n";

// Check 1: Duplicate attendance logs
echo "🔍 CHECK 1: Duplicate Attendance Logs\n";
echo "=====================================\n";

$duplicateLogs = AttendanceLog::selectRaw('
        attendance_machine_id,
        user_pin,
        datetime,
        COUNT(*) as count
    ')
    ->whereYear('datetime', $currentYear)
    ->whereMonth('datetime', $currentMonth)
    ->groupBy('attendance_machine_id', 'user_pin', 'datetime')
    ->havingRaw('COUNT(*) > 1')
    ->get();

if ($duplicateLogs->count() > 0) {
    echo "❌ DITEMUKAN DUPLIKASI DATA!\n";
    echo "📊 Total duplikasi: " . $duplicateLogs->count() . " records\n\n";
    
    foreach ($duplicateLogs as $duplicate) {
        echo "   - Machine ID: {$duplicate->attendance_machine_id}\n";
        echo "   - User PIN: {$duplicate->user_pin}\n";
        echo "   - DateTime: {$duplicate->datetime}\n";
        echo "   - Count: {$duplicate->count} records\n";
        echo "   ---\n";
    }
} else {
    echo "✅ TIDAK ADA DUPLIKASI DATA!\n";
    echo "📊 Semua data unik untuk {$monthName} {$currentYear}\n";
}

echo "\n";

// Check 2: Duplicate attendances
echo "🔍 CHECK 2: Duplicate Attendances\n";
echo "==================================\n";

$duplicateAttendances = Attendance::selectRaw('
        user_pin,
        date,
        COUNT(*) as count
    ')
    ->whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->groupBy('user_pin', 'date')
    ->havingRaw('COUNT(*) > 1')
    ->get();

if ($duplicateAttendances->count() > 0) {
    echo "❌ DITEMUKAN DUPLIKASI ATTENDANCE!\n";
    echo "📊 Total duplikasi: " . $duplicateAttendances->count() . " records\n\n";
    
    foreach ($duplicateAttendances as $duplicate) {
        echo "   - User PIN: {$duplicate->user_pin}\n";
        echo "   - Date: {$duplicate->date}\n";
        echo "   - Count: {$duplicate->count} records\n";
        echo "   ---\n";
    }
} else {
    echo "✅ TIDAK ADA DUPLIKASI ATTENDANCE!\n";
    echo "📊 Semua attendance unik untuk {$monthName} {$currentYear}\n";
}

echo "\n";

// Check 3: Data summary
echo "🔍 CHECK 3: Data Summary\n";
echo "========================\n";

$totalLogs = AttendanceLog::whereYear('datetime', $currentYear)
    ->whereMonth('datetime', $currentMonth)
    ->count();

$totalAttendances = Attendance::whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->count();

$uniqueLogs = AttendanceLog::whereYear('datetime', $currentYear)
    ->whereMonth('datetime', $currentMonth)
    ->distinct('attendance_machine_id', 'user_pin', 'datetime')
    ->count();

$uniqueAttendances = Attendance::whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->distinct('user_pin', 'date')
    ->count();

echo "📊 Attendance Logs:\n";
echo "   - Total records: {$totalLogs}\n";
echo "   - Unique records: {$uniqueLogs}\n";
echo "   - Duplicates: " . ($totalLogs - $uniqueLogs) . "\n\n";

echo "📊 Attendances:\n";
echo "   - Total records: {$totalAttendances}\n";
echo "   - Unique records: {$uniqueAttendances}\n";
echo "   - Duplicates: " . ($totalAttendances - $uniqueAttendances) . "\n\n";

// Check 4: Data per day
echo "🔍 CHECK 4: Data per Day\n";
echo "========================\n";

$dailyLogs = AttendanceLog::selectRaw('
        DATE(datetime) as date,
        COUNT(*) as total_logs
    ')
    ->whereYear('datetime', $currentYear)
    ->whereMonth('datetime', $currentMonth)
    ->groupBy('date')
    ->orderBy('date')
    ->get();

echo "📅 Daily Logs Summary:\n";
foreach ($dailyLogs as $day) {
    echo "   - {$day->date}: {$day->total_logs} logs\n";
}

echo "\n";

$dailyAttendances = Attendance::selectRaw('
        date,
        COUNT(*) as total_attendances
    ')
    ->whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->groupBy('date')
    ->orderBy('date')
    ->get();

echo "📅 Daily Attendances Summary:\n";
foreach ($dailyAttendances as $day) {
    echo "   - {$day->date}: {$day->total_attendances} attendances\n";
}

echo "\n";

// Check 5: Unique users
echo "🔍 CHECK 5: Unique Users\n";
echo "========================\n";

$uniqueUsersLogs = AttendanceLog::whereYear('datetime', $currentYear)
    ->whereMonth('datetime', $currentMonth)
    ->distinct('user_pin')
    ->count();

$uniqueUsersAttendances = Attendance::whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->distinct('user_pin')
    ->count();

echo "👥 Unique Users:\n";
echo "   - In Logs: {$uniqueUsersLogs} users\n";
echo "   - In Attendances: {$uniqueUsersAttendances} users\n";

echo "\n";

// Final verification
echo "🎯 FINAL VERIFICATION\n";
echo "=====================\n";

$hasDuplicates = ($duplicateLogs->count() > 0 || $duplicateAttendances->count() > 0);

if ($hasDuplicates) {
    echo "❌ VERIFIKASI GAGAL!\n";
    echo "📊 Ditemukan data duplikasi:\n";
    echo "   - Attendance Logs: " . $duplicateLogs->count() . " duplikasi\n";
    echo "   - Attendances: " . $duplicateAttendances->count() . " duplikasi\n";
    echo "\n💡 Rekomendasi:\n";
    echo "   - Cek log sync untuk melihat error\n";
    echo "   - Jalankan sync ulang jika diperlukan\n";
    echo "   - Hubungi admin jika masalah berlanjut\n";
} else {
    echo "✅ VERIFIKASI BERHASIL!\n";
    echo "📊 Tidak ada data duplikasi ditemukan\n";
    echo "🛡️  Data integrity terjaga dengan baik\n";
    echo "\n💡 Kesimpulan:\n";
    echo "   - Sistem anti-duplikasi berfungsi dengan baik\n";
    echo "   - Data aman dan bersih\n";
    echo "   - Sync bulanan aman untuk dijalankan\n";
}

echo "\n📋 Summary:\n";
echo "   - Bulan: {$monthName} {$currentYear}\n";
echo "   - Total Logs: {$totalLogs}\n";
echo "   - Total Attendances: {$totalAttendances}\n";
echo "   - Unique Users: {$uniqueUsersLogs}\n";
echo "   - Duplicates Found: " . ($hasDuplicates ? 'YES' : 'NO') . "\n";

echo "\n🎉 VERIFIKASI SELESAI!\n"; 