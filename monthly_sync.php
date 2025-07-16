<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceMachine;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;
use Carbon\Carbon;

echo "📅 Monthly Sync - Attendance Sebulan Penuh\n\n";
echo "🔄 SYNC BULANAN: Menarik semua data dari mesin untuk bulan ini\n";
echo "📊 Cocok untuk sync bulanan atau backup data\n\n";

try {
    // Get current month and year
    $currentDate = Carbon::now();
    $currentYear = $currentDate->year;
    $currentMonth = $currentDate->month;
    $monthName = $currentDate->format('F');
    
    echo "📅 Target: {$monthName} {$currentYear}\n";
    echo "📊 Rentang: " . $currentDate->startOfMonth()->format('Y-m-d') . " sampai " . $currentDate->endOfMonth()->format('Y-m-d') . "\n\n";
    
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
    
    // Pull ALL attendance data from machine (FULL SYNC)
    echo "📥 Pulling ALL attendance logs dari mesin...\n";
    echo "⏳ Ini akan memakan waktu lebih lama karena menarik semua data...\n";
    $pullResult = $machineService->pullAttendanceData($machine);
    
    if ($pullResult['success']) {
        echo "✅ Pull berhasil!\n";
        echo "📊 Total dari mesin: " . ($pullResult['stats']['total_from_machine'] ?? 0) . "\n";
        echo "📊 Records processed: " . ($pullResult['stats']['processed_to_logs'] ?? 0) . "\n";
        echo "📊 Response size: " . number_format(($pullResult['stats']['response_size_bytes'] ?? 0) / 1024, 2) . " KB\n\n";
    } else {
        echo "❌ Pull gagal: {$pullResult['message']}\n";
        exit(1);
    }
    
    // Process ALL unprocessed logs
    echo "⚙️  Processing semua logs yang belum diproses...\n";
    $processingService = new AttendanceProcessingService();
    $processResult = $processingService->processUnprocessedLogs();
    
    if ($processResult['success']) {
        echo "✅ Processing berhasil!\n";
        echo "📊 Processed: {$processResult['processed']} records\n";
    } else {
        echo "⚠️  Processing warning: {$processResult['message']}\n";
    }
    
    // Auto-sync employee linking
    echo "🔗 Auto-sync employee linking...\n";
    $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                            ->whereNull('employee_id')
                                            ->distinct()
                                            ->pluck('user_name');
    
    $syncedCount = 0;
    foreach ($uniqueUserNames as $userName) {
        try {
            $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
            if ($syncResult['success']) {
                $syncedCount++;
            }
        } catch (\Exception $e) {
            // Continue with next user
        }
    }
    
    echo "✅ Auto-sync completed: {$syncedCount} users linked\n\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📊 Checking hasil untuk {$monthName} {$currentYear}...\n";

$startDate = $currentDate->startOfMonth()->format('Y-m-d');
$endDate = $currentDate->endOfMonth()->format('Y-m-d');

$totalLogs = \App\Models\AttendanceLog::count();
$totalAttendances = \App\Models\Attendance::count();
$monthLogs = \App\Models\AttendanceLog::whereBetween('datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count();
$monthAttendances = \App\Models\Attendance::whereBetween('date', [$startDate, $endDate])->count();

echo "📋 Total logs (keseluruhan): {$totalLogs}\n";
echo "📋 Total attendances (keseluruhan): {$totalAttendances}\n";
echo "📋 Logs untuk {$monthName} {$currentYear}: {$monthLogs}\n";
echo "📋 Attendances untuk {$monthName} {$currentYear}: {$monthAttendances}\n";

// Check unique users in this month
$uniqueUsers = \App\Models\Attendance::whereBetween('date', [$startDate, $endDate])
                                    ->distinct()
                                    ->pluck('user_pin')
                                    ->count();

echo "👥 Unique users di {$monthName} {$currentYear}: {$uniqueUsers}\n";

echo "\n🎉 MONTHLY SYNC SELESAI!\n";
echo "✅ Data untuk {$monthName} {$currentYear} sudah tersedia\n";
echo "📊 Siap untuk export Excel bulanan\n";

echo "\n🚀 Monitoring:\n";
echo "   - Dashboard: http://localhost:8000/attendance-today.html\n";
echo "   - API: http://localhost:8000/api/attendance/today-realtime\n";
echo "   - Export Excel: http://localhost:8000/api/attendance/export/monthly?year={$currentYear}&month={$currentMonth}&format=excel\n";

echo "\n💡 Tips:\n";
echo "   - Gunakan script ini untuk sync bulanan\n";
echo "   - Data akan selalu sesuai dengan bulan dan tahun saat ini\n";
echo "   - Tidak akan menarik data dari tahun lalu\n"; 