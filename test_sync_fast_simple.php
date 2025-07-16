<?php

echo "🚀 TEST SYNC BULANAN CEPAT\n";
echo "==========================\n\n";

$url = 'http://localhost:8000/api/attendance/sync-current-month-fast';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 menit timeout

echo "⏳ Mengirim request ke: {$url}\n";
echo "⏱️  Timeout: 5 menit\n\n";

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "⏱️  Waktu eksekusi: {$executionTime} detik\n\n";

if ($httpCode === 200) {
    echo "✅ Sync bulanan cepat berhasil!\n\n";
    
    $result = json_decode($response, true);
    
    if (isset($result['data']['export_result'])) {
        $exportResult = $result['data']['export_result'];
        
        if ($exportResult['success']) {
            echo "📊 Export Excel Otomatis:\n";
            echo "   - File: " . $exportResult['filename'] . "\n";
            echo "   - Download URL: " . $exportResult['download_url'] . "\n";
            echo "   - Direct Download: " . $exportResult['direct_download_url'] . "\n";
            echo "   - Total Employees: " . $exportResult['total_employees'] . "\n";
            echo "   - Working Days: " . $exportResult['working_days'] . "\n";
            echo "   - Month: " . $exportResult['month'] . "\n\n";
            
            echo "📥 Untuk download file Excel:\n";
            echo "   - Buka browser dan akses: " . $exportResult['download_url'] . "\n";
            echo "   - Atau gunakan direct download: " . $exportResult['direct_download_url'] . "\n\n";
        } else {
            echo "❌ Export Excel gagal: " . ($exportResult['error'] ?? 'Unknown error') . "\n\n";
        }
    }
    
    if (isset($result['data']['monthly_stats'])) {
        $stats = $result['data']['monthly_stats'];
        echo "📊 Hasil Sync:\n";
        echo "   - Total dari mesin: " . ($stats['total_from_machine'] ?? 'N/A') . "\n";
        echo "   - Filtered bulan ini: " . ($stats['month_filtered'] ?? 'N/A') . "\n";
        echo "   - Processed to logs: " . ($stats['processed_to_logs'] ?? 'N/A') . "\n";
        echo "   - Processed to attendances: " . ($stats['processed_to_attendances'] ?? 'N/A') . "\n";
        echo "   - Sync Type: " . ($stats['sync_type'] ?? 'N/A') . "\n\n";
    }
    
} else {
    echo "❌ Sync bulanan cepat gagal!\n";
    echo "Response: " . $response . "\n";
}

echo "🎉 TEST SELESAI!\n"; 