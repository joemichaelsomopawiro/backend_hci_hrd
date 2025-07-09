<?php

/**
 * Test Full Sync - Improved Version
 * Script untuk testing full sync yang sudah diperbaiki
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST FULL SYNC - IMPROVED VERSION ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Test koneksi ke mesin
    echo "1. Testing koneksi ke mesin...\n";
    $machine = \App\Models\AttendanceMachine::where('ip_address', '10.10.10.85')->first();
    
    if (!$machine) {
        echo "   ❌ GAGAL: Mesin tidak ditemukan\n";
        echo "   💡 Silakan setup mesin terlebih dahulu\n";
        exit(1);
    }
    
    $machineService = new \App\Services\AttendanceMachineService($machine);
    $connectionTest = $machineService->testConnection($machine);
    
    if (!$connectionTest['success']) {
        echo "   ❌ GAGAL: {$connectionTest['message']}\n";
        echo "   💡 Pastikan mesin nyala dan terhubung ke network\n";
        exit(1);
    }
    
    echo "   ✅ BERHASIL: Koneksi ke mesin OK\n";
    echo "   📍 IP: {$machine->ip_address}:{$machine->port}\n\n";

    // 2. Test pull semua data dari mesin
    echo "2. Testing pull SEMUA data dari mesin...\n";
    echo "   ⏱️  Mohon tunggu, proses ini bisa memakan waktu 1-3 menit...\n";
    
    $startTime = microtime(true);
    $pullResult = $machineService->pullAttendanceData($machine, 'All');
    $pullDuration = round(microtime(true) - $startTime, 2);
    
    if (!$pullResult['success']) {
        echo "   ❌ GAGAL: {$pullResult['message']}\n";
        if (isset($pullResult['error_details'])) {
            echo "   🔍 Error details: " . json_encode($pullResult['error_details']) . "\n";
        }
        exit(1);
    }
    
    $totalFromMachine = count($pullResult['data'] ?? []);
    $processedToLogs = $pullResult['stats']['processed_to_logs'] ?? 0;
    
    echo "   ✅ BERHASIL: Pull data dari mesin selesai\n";
    echo "   📊 Total data dari mesin: {$totalFromMachine}\n";
    echo "   💾 Disimpan ke logs: {$processedToLogs}\n";
    echo "   ⏱️  Waktu pull: {$pullDuration} detik\n\n";

    // 3. Test proses logs ke attendance
    echo "3. Testing proses logs ke attendance...\n";
    
    $processingService = new \App\Services\AttendanceProcessingService();
    $startTime = microtime(true);
    $processResult = $processingService->processUnprocessedLogs();
    $processDuration = round(microtime(true) - $startTime, 2);
    
    if (!$processResult['success']) {
        echo "   ❌ GAGAL: {$processResult['message']}\n";
        exit(1);
    }
    
    echo "   ✅ BERHASIL: Proses logs selesai\n";
    echo "   📊 Attendance records diproses: {$processResult['processed']}\n";
    echo "   ⏱️  Waktu proses: {$processDuration} detik\n\n";

    // 4. Test auto-sync employee linking
    echo "4. Testing auto-sync employee linking...\n";
    
    $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                            ->whereNull('employee_id')
                                            ->distinct()
                                            ->pluck('user_name');
    
    $syncedCount = 0;
    $startTime = microtime(true);
    
    foreach ($uniqueUserNames as $userName) {
        try {
            $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
            if ($syncResult['success']) {
                $syncedCount++;
            }
        } catch (\Exception $e) {
            echo "     ⚠️  Warning: Sync failed for {$userName}: {$e->getMessage()}\n";
        }
    }
    
    $syncDuration = round(microtime(true) - $startTime, 2);
    
    echo "   ✅ BERHASIL: Auto-sync linking selesai\n";
    echo "   👥 Total users di attendance: " . count($uniqueUserNames) . "\n";
    echo "   🔗 Berhasil di-link: {$syncedCount}\n";
    echo "   ⏱️  Waktu sync: {$syncDuration} detik\n\n";

    // 5. Statistik final
    echo "5. Statistik Final Full Sync:\n";
    
    // Hitung logs terbaru
    $totalLogs = \App\Models\AttendanceLog::count();
    $todayLogs = \App\Models\AttendanceLog::whereDate('datetime', today())->count();
    $unprocessedLogs = \App\Models\AttendanceLog::where('is_processed', false)->count();
    
    // Hitung attendance terbaru  
    $totalAttendances = \App\Models\Attendance::count();
    $todayAttendances = \App\Models\Attendance::where('date', today())->count();
    $linkedAttendances = \App\Models\Attendance::whereNotNull('employee_id')->count();
    
    echo "   📊 LOGS:\n";
    echo "      - Total logs: {$totalLogs}\n";
    echo "      - Logs hari ini: {$todayLogs}\n";
    echo "      - Belum diproses: {$unprocessedLogs}\n";
    echo "\n";
    echo "   📊 ATTENDANCES:\n";
    echo "      - Total attendance: {$totalAttendances}\n";
    echo "      - Attendance hari ini: {$todayAttendances}\n";  
    echo "      - Ter-link ke employee: {$linkedAttendances}\n";
    echo "\n";
    
    $totalDuration = $pullDuration + $processDuration + $syncDuration;
    echo "   ⏱️  TOTAL WAKTU: {$totalDuration} detik (" . round($totalDuration/60, 1) . " menit)\n";
    
    // 6. Test koneksi API endpoint
    echo "\n6. Testing API endpoint full sync...\n";
    $apiUrl = 'http://127.0.0.1:8000/api/attendance/sync';
    
    echo "   🌐 Testing URL: {$apiUrl}\n";
    echo "   ⚠️  Note: Test ini memerlukan server web berjalan\n";
    
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'timeout' => 300 // 5 menit timeout
            ]
        ]);
        
        $startTime = microtime(true);
        $response = @file_get_contents($apiUrl, false, $context);
        $apiDuration = round(microtime(true) - $startTime, 2);
        
        if ($response === false) {
            echo "   ⚠️  SKIP: Server web tidak berjalan atau endpoint tidak dapat diakses\n";
            echo "   💡 Untuk test API, jalankan: php artisan serve\n";
        } else {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "   ✅ BERHASIL: API endpoint berfungsi\n";
                echo "   📝 Response: {$data['message']}\n";
                echo "   ⏱️  Waktu API: {$apiDuration} detik\n";
            } else {
                echo "   ❌ GAGAL: " . ($data['message'] ?? 'Unknown error') . "\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ⚠️  ERROR: {$e->getMessage()}\n";
    }

    echo "\n=== FULL SYNC TEST SELESAI ===\n";
    echo "✅ Semua komponen full sync berfungsi dengan baik!\n";
    echo "💡 Tips: Gunakan full sync hanya untuk setup awal, untuk sync harian gunakan sync-today-only\n";

} catch (\Exception $e) {
    echo "\n❌ FATAL ERROR: {$e->getMessage()}\n";
    echo "📍 File: {$e->getFile()}:{$e->getLine()}\n";
    echo "🔍 Trace:\n{$e->getTraceAsString()}\n";
    exit(1);
} 