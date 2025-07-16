<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Bootstrap Laravel
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 TEST SYNC BULANAN CEPAT (FAST VERSION)\n";
echo "==========================================\n\n";

try {
    $currentDate = \Carbon\Carbon::now();
    $currentYear = $currentDate->year;
    $currentMonth = $currentDate->month;
    $monthName = $currentDate->format('F');
    
    echo "📅 Bulan saat ini: {$monthName} {$currentYear}\n";
    echo "📊 Rentang: {$currentDate->startOfMonth()->format('Y-m-d')} sampai {$currentDate->endOfMonth()->format('Y-m-d')}\n\n";

    // Test via API
    echo "🌐 Testing via API (FAST VERSION)...\n";
    
    $client = new \GuzzleHttp\Client([
        'timeout' => 180, // 3 menit timeout
        'connect_timeout' => 30
    ]);
    
    $startTime = microtime(true);
    
    $response = $client->post('http://localhost:8000/api/attendance/sync-current-month-fast', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]
    ]);
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    echo "HTTP Code: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ Sync bulanan cepat berhasil!\n";
        echo "⏱️  Waktu eksekusi: {$executionTime} detik\n\n";
        
        $result = json_decode($response->getBody(), true);
        
        if (isset($result['data']['monthly_stats'])) {
            $stats = $result['data']['monthly_stats'];
            echo "📊 Hasil Sync Cepat:\n";
            echo "   - Bulan: {$monthName} {$currentYear}\n";
            echo "   - Total dari mesin: " . ($stats['total_from_machine'] ?? 'N/A') . "\n";
            echo "   - Filtered bulan ini: " . ($stats['month_filtered'] ?? 'N/A') . "\n";
            echo "   - Processed to logs: " . ($stats['processed_to_logs'] ?? 'N/A') . "\n";
            echo "   - Processed to attendances: " . ($stats['processed_to_attendances'] ?? 'N/A') . "\n";
            echo "   - Auto-sync users: " . ($result['data']['auto_sync_result']['synced_count'] ?? 'N/A') . "/" . ($result['data']['auto_sync_result']['total_users'] ?? 'N/A') . "\n";
            echo "   - Employee ID updates: " . ($result['data']['employee_id_sync']['updated_count'] ?? 'N/A') . "\n";
            echo "   - Sync Type: " . ($stats['sync_type'] ?? 'N/A') . "\n\n";
            
            echo "📅 Rentang tanggal:\n";
            echo "   - Start: " . ($stats['start_date'] ?? 'N/A') . "\n";
            echo "   - End: " . ($stats['end_date'] ?? 'N/A') . "\n\n";
        }
        
        // Check database results
        echo "📊 Checking database results...\n";
        
        $db = \Illuminate\Support\Facades\DB::connection();
        
        // Get total logs for current month
        $totalLogs = $db->table('attendance_logs')
            ->whereYear('datetime', $currentYear)
            ->whereMonth('datetime', $currentMonth)
            ->count();
            
        // Get total attendances for current month
        $totalAttendances = $db->table('attendances')
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->count();
            
        // Get unique users for current month
        $uniqueUsers = $db->table('attendances')
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->whereNotNull('user_pin')
            ->distinct('user_pin')
            ->count('user_pin');
            
        echo "📋 Database Summary:\n";
        echo "   - Total logs untuk {$monthName} {$currentYear}: {$totalLogs}\n";
        echo "   - Total attendances untuk {$monthName} {$currentYear}: {$totalAttendances}\n";
        echo "👥 Unique users di {$monthName} {$currentYear}: {$uniqueUsers}\n\n";
        
    } else {
        echo "❌ Sync bulanan cepat gagal!\n";
        echo "Error: " . $response->getBody() . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "🎉 TEST SELESAI!\n";
echo "💡 Tips:\n";
echo "   - Gunakan script ini untuk test sync bulanan cepat\n";
echo "   - Versi cepat menggunakan optimasi performa\n";
echo "   - Data akan selalu sesuai dengan bulan dan tahun saat ini\n";
echo "   - Tidak akan menarik data dari tahun lalu\n";
echo "   - Siap untuk export Excel bulanan\n";
echo "   - Waktu eksekusi lebih cepat dari versi normal\n"; 