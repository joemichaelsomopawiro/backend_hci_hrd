<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Bootstrap Laravel
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 SYNC & EXPORT BULANAN CEPAT OTOMATIS\n";
echo "========================================\n\n";

try {
    $currentDate = \Carbon\Carbon::now();
    $currentYear = $currentDate->year;
    $currentMonth = $currentDate->month;
    $monthName = $currentDate->format('F');
    
    echo "📅 Target: {$monthName} {$currentYear}\n";
    echo "📊 Rentang: {$currentDate->startOfMonth()->format('Y-m-d')} sampai {$currentDate->endOfMonth()->format('Y-m-d')}\n\n";

    $client = new \GuzzleHttp\Client([
        'timeout' => 180,
        'connect_timeout' => 30
    ]);

    // STEP 1: Fast Monthly Sync
    echo "⏳ Menarik data untuk {$monthName} {$currentYear} (FAST VERSION)...\n";
    
    $startTime = microtime(true);
    
    $syncResponse = $client->post('http://localhost:8000/api/attendance/sync-current-month-fast', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]
    ]);
    
    $syncEndTime = microtime(true);
    $syncExecutionTime = round($syncEndTime - $startTime, 2);
    
    if ($syncResponse->getStatusCode() === 200) {
        echo "✅ Sync bulanan cepat berhasil!\n";
        echo "⏱️  Waktu sync: {$syncExecutionTime} detik\n";
        
        $syncResult = json_decode($syncResponse->getBody(), true);
        
        if (isset($syncResult['data']['monthly_stats'])) {
            $stats = $syncResult['data']['monthly_stats'];
            echo "📊 Hasil Sync Cepat:\n";
            echo "   - Total dari mesin: " . ($stats['total_from_machine'] ?? 'N/A') . "\n";
            echo "   - Filtered bulan ini: " . ($stats['month_filtered'] ?? 'N/A') . "\n";
            echo "   - Processed to logs: " . ($stats['processed_to_logs'] ?? 'N/A') . "\n";
            echo "   - Processed to attendances: " . ($stats['processed_to_attendances'] ?? 'N/A') . "\n";
            echo "   - Auto-sync users: " . ($syncResult['data']['auto_sync_result']['synced_count'] ?? 'N/A') . "/" . ($syncResult['data']['auto_sync_result']['total_users'] ?? 'N/A') . "\n";
            echo "   - Employee ID updates: " . ($syncResult['data']['employee_id_sync']['updated_count'] ?? 'N/A') . "\n\n";
        }
    } else {
        echo "❌ Sync bulanan cepat gagal!\n";
        echo "Error: " . $syncResponse->getBody() . "\n";
        exit(1);
    }

    // STEP 2: Export Excel bulanan
    echo "📊 STEP 2: Export Excel bulanan...\n";
    echo "⏳ Membuat file Excel untuk {$monthName} {$currentYear}...\n";
    
    $exportStartTime = microtime(true);
    
    $exportResponse = $client->get('http://localhost:8000/api/attendance/export/monthly', [
        'headers' => [
            'Accept' => 'application/json'
        ]
    ]);
    
    $exportEndTime = microtime(true);
    $exportExecutionTime = round($exportEndTime - $exportStartTime, 2);
    
    if ($exportResponse->getStatusCode() === 200) {
        echo "✅ Export Excel berhasil!\n";
        echo "⏱️  Waktu export: {$exportExecutionTime} detik\n";
        
        $exportResult = json_decode($exportResponse->getBody(), true);
        
        if (isset($exportResult['data'])) {
            $exportData = $exportResult['data'];
            echo "📁 File: " . ($exportData['filename'] ?? 'N/A') . "\n";
            echo "🔗 Download URL: " . ($exportData['download_url'] ?? 'N/A') . "\n";
            echo "👥 Total employees: " . ($exportData['total_employees'] ?? 'N/A') . "\n";
            echo "📅 Working days: " . ($exportData['working_days'] ?? 'N/A') . "\n";
            echo "📊 Month: " . ($exportData['month'] ?? 'N/A') . "\n\n";
            
            // Auto-download file
            echo "📥 Auto-downloading file...\n";
            
            $downloadResponse = $client->get($exportData['download_url']);
            
            if ($downloadResponse->getStatusCode() === 200) {
                $filename = $exportData['filename'];
                $filepath = "exports/{$filename}";
                
                // Create exports directory if not exists
                if (!is_dir('exports')) {
                    mkdir('exports', 0755, true);
                }
                
                file_put_contents($filepath, $downloadResponse->getBody());
                echo "✅ File berhasil didownload ke: {$filepath}\n\n";
            } else {
                echo "⚠️  Gagal auto-download file\n";
            }
        }
    } else {
        echo "❌ Export Excel gagal!\n";
        echo "Error: " . $exportResponse->getBody() . "\n";
    }

    $totalExecutionTime = round($exportEndTime - $startTime, 2);
    
    echo "🎉 SYNC & EXPORT SELESAI!\n";
    echo "==========================\n";
    echo "✅ Data untuk {$monthName} {$currentYear} sudah tersedia\n";
    echo "✅ File Excel sudah dibuat dan didownload\n";
    echo "📊 Siap untuk analisis dan laporan\n\n";
    
    echo "📋 Summary:\n";
    echo "   - Sync: " . ($stats['processed_to_logs'] ?? 'N/A') . " records processed\n";
    echo "   - Export: " . ($exportData['total_employees'] ?? 'N/A') . " employees, " . ($exportData['working_days'] ?? 'N/A') . " working days\n";
    echo "   - File: " . ($exportData['filename'] ?? 'N/A') . "\n";
    echo "   - Total waktu: {$totalExecutionTime} detik\n\n";
    
    echo "💡 Tips:\n";
    echo "   - File Excel tersimpan di folder 'exports/'\n";
    echo "   - Data sudah terintegrasi dengan status cuti\n";
    echo "   - Siap untuk laporan ke HR/GA\n";
    echo "   - Versi cepat menggunakan optimasi performa\n\n";
    
    echo "🚀 Monitoring:\n";
    echo "   - Dashboard: http://localhost:8000/attendance-today.html\n";
    echo "   - API: http://localhost:8000/api/attendance/today-realtime\n";
    echo "   - Export URL: " . ($exportData['download_url'] ?? 'N/A') . "\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 