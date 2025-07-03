<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceMachine;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;

echo "📡 Pull Data Hari Ini dari Machine X304...\n\n";
echo "🔄 HANYA PULL data hari ini - TIDAK mengubah apapun di mesin\n";
echo "⚡ OPTIMIZED: Lebih cepat karena hanya proses data hari ini\n\n";

try {
    // Get machine
    $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();
    if (!$machine) {
        echo "❌ Machine X304 tidak ditemukan!\n";
        exit(1);
    }
    
    echo "📡 Machine: {$machine->name} ({$machine->ip_address})\n";
    
    $machineService = new AttendanceMachineService($machine);
    
    // Test connection
    echo "🔗 Testing connection...\n";
    $connectionTest = $machineService->testConnection($machine);
    if (!$connectionTest['success']) {
        echo "❌ Koneksi gagal: {$connectionTest['message']}\n";
        exit(1);
    }
    echo "✅ Connected successfully!\n\n";
    
    // Pull attendance data - HANYA HARI INI
    $today = date('Y-m-d');
    echo "📥 Pulling attendance logs untuk hari ini ({$today})...\n";
    $pullResult = $machineService->pullTodayAttendanceData($machine, $today);
    
    if ($pullResult['success']) {
        echo "✅ Pull berhasil!\n";
        echo "📊 Total dari mesin: " . ($pullResult['stats']['total_from_machine'] ?? 0) . "\n";
        echo "📊 Filtered hari ini: " . ($pullResult['stats']['today_filtered'] ?? 0) . "\n";
        echo "📊 Records processed: " . ($pullResult['stats']['processed'] ?? 0) . "\n\n";
    } else {
        echo "❌ Pull gagal: {$pullResult['message']}\n";
        exit(1);
    }
    
    // Process attendance untuk hari ini
    echo "⚙️  Processing attendance untuk hari ini...\n";
    $processingService = new AttendanceProcessingService();
    $processResult = $processingService->processTodayOnly($today);
    
    if ($processResult['success']) {
        echo "✅ Processing berhasil!\n";
        echo "📊 Processed: {$processResult['processed']} users\n";
    } else {
        echo "⚠️  Processing warning: {$processResult['message']}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📊 Checking hasil...\n";

$totalLogs = \App\Models\AttendanceLog::count();
$totalAttendances = \App\Models\Attendance::count();
$todayLogs = \App\Models\AttendanceLog::whereDate('datetime', $today)->count();
$todayAttendances = \App\Models\Attendance::where('date', $today)->count();

echo "📋 Total logs: {$totalLogs}\n";
echo "📋 Total attendances: {$totalAttendances}\n";
echo "📋 Logs hari ini: {$todayLogs}\n";
echo "📋 Attendances hari ini: {$todayAttendances}\n";

echo "\n🎉 PULL HARI INI SELESAI!\n";
echo "✅ Data hari ini ({$today}) dari mesin X304 sudah tersedia\n";
echo "✅ Ready untuk attendance monitoring\n";
echo "⚡ Optimized: Hanya data hari ini yang diproses - lebih cepat!\n";

echo "\n🚀 Buka: http://localhost:8000/attendance-today.html\n";
echo "🌐 API: http://localhost:8000/api/attendance/today-realtime\n";
?> 