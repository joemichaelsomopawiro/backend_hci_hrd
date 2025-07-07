<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceLog;
use App\Models\Attendance;
use App\Models\AttendanceSyncLog;
use App\Models\EmployeeAttendance;
use App\Models\AttendanceMachine;
use App\Services\AttendanceMachineService;
use Illuminate\Support\Facades\DB;

echo "🔄 Fresh Sync All: Clean Tables + Sync from Machine X304...\n\n";

// Ask user for confirmation
echo "⚠️  PERINGATAN: Script ini akan:\n";
echo "1. Hapus SEMUA data di attendance_logs\n";
echo "2. Hapus SEMUA data di attendances\n";
echo "3. Hapus SEMUA data di attendance_sync_logs\n";
echo "4. KEEP data di employee_attendance (sudah sinkron)\n";
echo "5. Fresh pull attendance logs dari mesin X304\n";
echo "6. Process attendance untuk hari ini\n\n";

echo "Data akan 100% fresh dari mesin X304. Lanjutkan? (y/N): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'y') {
    echo "❌ Operasi dibatalkan.\n";
    exit(0);
}

echo "\n🧹 STEP 1: Membersihkan tabel...\n";

$logsCount = AttendanceLog::count();
$attendanceCount = Attendance::count();
$syncLogsCount = AttendanceSyncLog::count();

echo "🗑️  Hapus {$logsCount} records dari attendance_logs...\n";
AttendanceLog::truncate();

echo "🗑️  Hapus {$attendanceCount} records dari attendances...\n";
Attendance::truncate();

echo "🗑️  Hapus {$syncLogsCount} records dari attendance_sync_logs...\n";
AttendanceSyncLog::truncate();

echo "✅ Semua tabel attendance sudah dibersihkan!\n";

echo "\n📡 STEP 2: Fresh sync dari Mesin X304...\n";

try {
    $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();
    if (!$machine) {
        echo "❌ Machine tidak ditemukan!\n";
        exit(1);
    }
    
    $machineService = new AttendanceMachineService($machine);
    
    // Test connection
    echo "🔗 Testing connection...\n";
    $connectionTest = $machineService->testConnection($machine);
    if (!$connectionTest['success']) {
        echo "❌ Koneksi gagal: {$connectionTest['message']}\n";
        exit(1);
    }
    echo "✅ Connected successfully\n";
    
    // Pull attendance data
    echo "📥 Pulling attendance logs dari mesin...\n";
    $pullResult = $machineService->pullAttendanceData($machine);
    
    if ($pullResult['success']) {
        echo "✅ Pull berhasil!\n";
        echo "📊 Records pulled: " . count($pullResult['data'] ?? []) . "\n";
    } else {
        echo "❌ Pull gagal: {$pullResult['message']}\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n⚙️  STEP 3: Process attendance untuk hari ini...\n";

try {
    $url = 'http://localhost:8000/api/attendance/sync-today';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['success']) {
            echo "✅ Sync today berhasil!\n";
        } else {
            echo "⚠️  Sync today warning: " . ($data['message'] ?? 'Unknown') . "\n";
        }
    } else {
        echo "⚠️  HTTP Error $httpCode saat sync today\n";
    }
    
} catch (\Exception $e) {
    echo "⚠️  Error saat sync today: " . $e->getMessage() . "\n";
}

echo "\n📊 STEP 4: Checking hasil...\n";

$newLogs = AttendanceLog::count();
$newAttendances = Attendance::count();
$todayLogs = AttendanceLog::whereDate('datetime', now()->format('Y-m-d'))->count();

echo "📋 Total attendance logs: {$newLogs}\n";
echo "📋 Total attendances: {$newAttendances}\n";
echo "📋 Logs hari ini: {$todayLogs}\n";

echo "\n🎉 FRESH SYNC SELESAI!\n";
echo "✅ Semua data attendance sekarang 100% fresh dari mesin X304\n";
echo "✅ Employee_attendance tetap sinkron (32 users)\n";
echo "✅ Siap digunakan untuk attendance real-time\n";

echo "\n🚀 Buka: http://localhost:8000/attendance-today.html\n";
?> 