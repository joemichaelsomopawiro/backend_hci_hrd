<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceMachine;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;

echo "📅 Daily Sync - Attendance Hari Ini\n\n";
echo "⚡ OPTIMIZED: Hanya sync data hari ini - super cepat!\n";
echo "🔄 Cocok untuk sync rutin harian\n\n";

try {
    // Get target date (default today)
    $targetDate = $argv[1] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
        echo "❌ Format tanggal tidak valid. Gunakan format: Y-m-d\n";
        echo "📋 Contoh: php daily_sync.php 2025-01-27\n";
        exit(1);
    }
    
    echo "📅 Target date: {$targetDate}\n";
    
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
    
    // Pull attendance data - HANYA TARGET DATE
    echo "📥 Pulling attendance logs untuk {$targetDate}...\n";
    $pullResult = $machineService->pullTodayAttendanceData($machine, $targetDate);
    
    if ($pullResult['success']) {
        echo "✅ Pull berhasil!\n";
        echo "📊 Total dari mesin: " . ($pullResult['stats']['total_from_machine'] ?? 0) . "\n";
        echo "📊 Filtered target date: " . ($pullResult['stats']['today_filtered'] ?? 0) . "\n";
        echo "📊 Records processed: " . ($pullResult['stats']['processed'] ?? 0) . "\n\n";
    } else {
        echo "❌ Pull gagal: {$pullResult['message']}\n";
        exit(1);
    }
    
    // Process attendance untuk target date
    echo "⚙️  Processing attendance untuk {$targetDate}...\n";
    $processingService = new AttendanceProcessingService();
    $processResult = $processingService->processTodayOnly($targetDate);
    
    if ($processResult['success']) {
        echo "✅ Processing berhasil!\n";
        echo "📊 Processed: {$processResult['processed']} users untuk {$targetDate}\n";
    } else {
        echo "⚠️  Processing warning: {$processResult['message']}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📊 Checking hasil untuk {$targetDate}...\n";

$totalLogs = \App\Models\AttendanceLog::count();
$totalAttendances = \App\Models\Attendance::count();
$targetLogs = \App\Models\AttendanceLog::whereDate('datetime', $targetDate)->count();
$targetAttendances = \App\Models\Attendance::where('date', $targetDate)->count();

echo "📋 Total logs (keseluruhan): {$totalLogs}\n";
echo "📋 Total attendances (keseluruhan): {$totalAttendances}\n";
echo "📋 Logs untuk {$targetDate}: {$targetLogs}\n";
echo "📋 Attendances untuk {$targetDate}: {$targetAttendances}\n";

echo "\n🎉 DAILY SYNC SELESAI!\n";
echo "✅ Data untuk {$targetDate} sudah tersedia\n";
echo "⚡ Optimized: Hanya data target date yang diproses - super cepat!\n";

echo "\n🚀 Monitoring:\n";
echo "   - Dashboard: http://localhost:8000/attendance-today.html\n";
echo "   - API: http://localhost:8000/api/attendance/today-realtime\n";
echo "   - Sync via API: POST http://localhost:8000/api/attendance/sync-today-only\n";

echo "\n💡 Tips:\n";
echo "   - Gunakan script ini untuk sync rutin harian\n";
echo "   - Untuk sync awal (semua data), gunakan fresh_sync_all.php\n";
echo "   - Untuk sync manual tanggal tertentu: php daily_sync.php 2025-01-27\n";
?> 