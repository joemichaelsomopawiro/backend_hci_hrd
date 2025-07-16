<?php
/**
 * Script untuk sync bulanan dan export Excel otomatis
 * Menjalankan sync bulanan terlebih dahulu, lalu export Excel
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;

echo "🔄 SYNC & EXPORT BULANAN OTOMATIS\n";
echo "==================================\n\n";

// Get current month info
$currentDate = Carbon::now();
$currentYear = $currentDate->year;
$currentMonth = $currentDate->month;
$monthName = $currentDate->format('F');

echo "📅 Target: {$monthName} {$currentYear}\n";
echo "📊 Rentang: " . $currentDate->startOfMonth()->format('Y-m-d') . " sampai " . $currentDate->endOfMonth()->format('Y-m-d') . "\n\n";

// Step 1: Sync bulanan
echo "🔄 STEP 1: Sync bulanan dari mesin...\n";
echo "⏳ Menarik data untuk {$monthName} {$currentYear}...\n\n";

$syncUrl = 'http://localhost:8000/api/attendance/sync-current-month';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $syncUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 menit timeout
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$syncResponse = curl_exec($ch);
$syncHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($syncHttpCode === 200) {
    $syncResult = json_decode($syncResponse, true);
    
    if ($syncResult['success']) {
        echo "✅ Sync bulanan berhasil!\n";
        echo "📊 Hasil Sync:\n";
        echo "   - Total dari mesin: " . ($syncResult['data']['monthly_stats']['total_from_machine'] ?? 0) . "\n";
        echo "   - Filtered bulan ini: " . ($syncResult['data']['monthly_stats']['month_filtered'] ?? 0) . "\n";
        echo "   - Processed to logs: " . ($syncResult['data']['monthly_stats']['processed_to_logs'] ?? 0) . "\n";
        echo "   - Processed to attendances: " . ($syncResult['data']['monthly_stats']['processed_to_attendances'] ?? 0) . "\n";
        echo "   - Auto-sync users: " . ($syncResult['data']['auto_sync_result']['synced_count'] ?? 0) . "/" . ($syncResult['data']['auto_sync_result']['total_users'] ?? 0) . "\n";
        echo "   - Employee ID updates: " . ($syncResult['data']['employee_id_sync']['updated_count'] ?? 0) . "\n\n";
    } else {
        echo "❌ Sync gagal: " . $syncResult['message'] . "\n";
        exit(1);
    }
} else {
    echo "❌ HTTP Error saat sync: " . $syncHttpCode . "\n";
    echo "Response: " . $syncResponse . "\n";
    exit(1);
}

// Step 2: Export Excel
echo "📊 STEP 2: Export Excel bulanan...\n";
echo "⏳ Membuat file Excel untuk {$monthName} {$currentYear}...\n\n";

$exportUrl = "http://localhost:8000/api/attendance/export/monthly?year={$currentYear}&month={$currentMonth}&format=excel";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $exportUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 menit timeout
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$exportResponse = curl_exec($ch);
$exportHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($exportHttpCode === 200) {
    $exportResult = json_decode($exportResponse, true);
    
    if ($exportResult['success']) {
        echo "✅ Export Excel berhasil!\n";
        echo "📁 File: " . $exportResult['data']['filename'] . "\n";
        echo "🔗 Download URL: " . $exportResult['data']['download_url'] . "\n";
        echo "👥 Total employees: " . $exportResult['data']['total_employees'] . "\n";
        echo "📅 Working days: " . $exportResult['data']['working_days'] . "\n";
        echo "📊 Month: " . $exportResult['data']['month'] . "\n\n";
        
        // Auto download file
        echo "📥 Auto-downloading file...\n";
        $downloadUrl = $exportResult['data']['download_url'];
        $filename = $exportResult['data']['filename'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $fileContent = curl_exec($ch);
        $downloadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($downloadHttpCode === 200 && !empty($fileContent)) {
            $localPath = "exports/" . $filename;
            
            // Create exports directory if not exists
            if (!file_exists('exports')) {
                mkdir('exports', 0755, true);
            }
            
            file_put_contents($localPath, $fileContent);
            echo "✅ File berhasil didownload ke: {$localPath}\n";
        } else {
            echo "⚠️  Gagal auto-download, silakan download manual dari URL di atas\n";
        }
        
    } else {
        echo "❌ Export gagal: " . $exportResult['message'] . "\n";
        exit(1);
    }
} else {
    echo "❌ HTTP Error saat export: " . $exportHttpCode . "\n";
    echo "Response: " . $exportResponse . "\n";
    exit(1);
}

// Step 3: Summary
echo "\n🎉 SYNC & EXPORT SELESAI!\n";
echo "==========================\n";
echo "✅ Data untuk {$monthName} {$currentYear} sudah tersedia\n";
echo "✅ File Excel sudah dibuat dan didownload\n";
echo "📊 Siap untuk analisis dan laporan\n";

echo "\n📋 Summary:\n";
echo "   - Sync: " . ($syncResult['data']['monthly_stats']['processed_to_attendances'] ?? 0) . " records processed\n";
echo "   - Export: " . ($exportResult['data']['total_employees'] ?? 0) . " employees, " . ($exportResult['data']['working_days'] ?? 0) . " working days\n";
echo "   - File: " . ($exportResult['data']['filename'] ?? 'N/A') . "\n";

echo "\n💡 Tips:\n";
echo "   - File Excel tersimpan di folder 'exports/'\n";
echo "   - Data sudah terintegrasi dengan status cuti\n";
echo "   - Siap untuk laporan ke HR/GA\n";

echo "\n🚀 Monitoring:\n";
echo "   - Dashboard: http://localhost:8000/attendance-today.html\n";
echo "   - API: http://localhost:8000/api/attendance/today-realtime\n";
echo "   - Export URL: " . ($exportResult['data']['download_url'] ?? 'N/A') . "\n"; 