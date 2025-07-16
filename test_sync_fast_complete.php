<?php
/**
 * Test Sync Bulanan Cepat dengan Auto-Export Excel
 * Script untuk menguji endpoint sync bulanan cepat yang sudah diperbaiki
 */

// Konfigurasi
$baseUrl = 'http://127.0.0.1:8000/api';

echo "=== TEST SYNC BULANAN CEPAT LENGKAP ===\n";
echo "Base URL: {$baseUrl}\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Sync bulanan cepat
echo "1. Menguji sync bulanan cepat...\n";
$syncUrl = "{$baseUrl}/attendance/sync-current-month-fast";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $syncUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 menit timeout
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$totalTime = round($endTime - $startTime, 2);

curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Waktu eksekusi: {$totalTime} detik\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    
    if ($result && isset($result['success'])) {
        if ($result['success']) {
            echo "✓ Sync berhasil!\n";
            echo "  Message: {$result['message']}\n";
            
            // Cek data response
            if (isset($result['data'])) {
                echo "  Data keys: " . implode(', ', array_keys($result['data'])) . "\n";
                
                // Cek info sync
                if (isset($result['data']['sync_info'])) {
                    $syncInfo = $result['data']['sync_info'];
                    echo "  Sync Info:\n";
                    foreach ($syncInfo as $key => $value) {
                        echo "    {$key}: {$value}\n";
                    }
                }
                
                // Cek info export
                if (isset($result['data']['export_info'])) {
                    $exportInfo = $result['data']['export_info'];
                    echo "  Export Info:\n";
                    foreach ($exportInfo as $key => $value) {
                        echo "    {$key}: {$value}\n";
                    }
                }
                
                // Cek URL download
                if (isset($result['data']['download_url'])) {
                    $downloadUrl = $result['data']['download_url'];
                    $filename = $result['data']['filename'] ?? 'unknown.xls';
                    
                    echo "  Download URL: {$downloadUrl}\n";
                    echo "  Filename: {$filename}\n";
                    
                    // Test 2: Verifikasi file Excel
                    echo "\n2. Memverifikasi file Excel...\n";
                    
                    $filePath = __DIR__ . '/storage/app/public/exports/' . $filename;
                    if (file_exists($filePath)) {
                        $fileSize = filesize($filePath);
                        $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
                        
                        echo "✓ File Excel ditemukan\n";
                        echo "  Path: {$filePath}\n";
                        echo "  Size: " . number_format($fileSize) . " bytes\n";
                        echo "  Modified: {$fileTime}\n";
                        
                        // Cek isi file
                        $fileContent = file_get_contents($filePath);
                        if (strpos($fileContent, '<html') !== false) {
                            echo "✓ File berisi HTML (format Excel valid)\n";
                        } else {
                            echo "⚠ File tidak berisi HTML\n";
                        }
                        
                        // Test 3: Test download file
                        echo "\n3. Menguji download file...\n";
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, true);
                        curl_setopt($ch, CURLOPT_NOBODY, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        
                        $downloadResponse = curl_exec($ch);
                        $downloadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                        $headers = substr($downloadResponse, 0, $headerSize);
                        
                        curl_close($ch);
                        
                        if ($downloadHttpCode === 200) {
                            echo "✓ Download endpoint berfungsi\n";
                            
                            // Parse headers
                            $headerLines = explode("\n", $headers);
                            foreach ($headerLines as $line) {
                                if (stripos($line, 'Content-Type') !== false) {
                                    echo "  " . trim($line) . "\n";
                                }
                                if (stripos($line, 'Content-Disposition') !== false) {
                                    echo "  " . trim($line) . "\n";
                                }
                                if (stripos($line, 'Content-Length') !== false) {
                                    echo "  " . trim($line) . "\n";
                                }
                            }
                        } else {
                            echo "✗ Download endpoint gagal (HTTP {$downloadHttpCode})\n";
                        }
                        
                    } else {
                        echo "✗ File Excel TIDAK ditemukan\n";
                        echo "  Expected path: {$filePath}\n";
                    }
                    
                } else {
                    echo "⚠ Tidak ada URL download dalam response\n";
                }
                
            } else {
                echo "⚠ Response tidak memiliki data\n";
            }
            
        } else {
            echo "✗ Sync gagal\n";
            echo "  Message: {$result['message']}\n";
            
            if (isset($result['errors'])) {
                echo "  Errors:\n";
                foreach ($result['errors'] as $field => $errors) {
                    echo "    {$field}: " . implode(', ', $errors) . "\n";
                }
            }
        }
    } else {
        echo "✗ Response tidak valid JSON\n";
        echo "Response: {$response}\n";
    }
} else {
    echo "✗ Request gagal (HTTP {$httpCode})\n";
    echo "Response: {$response}\n";
}

echo "\n";

// Test 4: Cek database untuk data yang baru di-sync
echo "4. Mengecek data di database...\n";

try {
    // Load Laravel environment
    require_once __DIR__ . '/vendor/autoload.php';
    
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    // Cek data attendance untuk bulan ini
    $currentYear = date('Y');
    $currentMonth = date('m');
    
    $attendanceCount = \App\Models\Attendance::whereYear('date', $currentYear)
        ->whereMonth('date', $currentMonth)
        ->count();
    
    $employeeAttendanceCount = \App\Models\EmployeeAttendance::where('is_active', true)->count();
    
    echo "  Total attendance bulan ini: {$attendanceCount}\n";
    echo "  Total employee aktif: {$employeeAttendanceCount}\n";
    
    // Cek sync log terbaru
    $latestSyncLog = \App\Models\AttendanceSyncLog::where('operation', 'pull_current_month_data')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($latestSyncLog) {
        echo "  Sync log terbaru:\n";
        echo "    Operation: {$latestSyncLog->operation}\n";
        echo "    Status: {$latestSyncLog->status}\n";
        echo "    Records processed: {$latestSyncLog->records_processed}\n";
        echo "    Created: {$latestSyncLog->created_at}\n";
    } else {
        echo "  Tidak ada sync log ditemukan\n";
    }
    
} catch (Exception $e) {
    echo "  Error saat cek database: {$e->getMessage()}\n";
}

echo "\n";

// Test 5: Cek storage dan file
echo "5. Mengecek storage dan file...\n";

$storagePath = __DIR__ . '/storage/app/public/exports';
$publicPath = __DIR__ . '/public/storage/exports';

if (is_dir($storagePath)) {
    $files = glob($storagePath . '/*.xls');
    echo "  Files di storage: " . count($files) . "\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $time = date('Y-m-d H:i:s', filemtime($file));
        echo "    {$filename} ({$size} bytes, {$time})\n";
    }
} else {
    echo "  Storage directory tidak ditemukan\n";
}

if (is_dir($publicPath)) {
    $publicFiles = glob($publicPath . '/*.xls');
    echo "  Files di public: " . count($publicFiles) . "\n";
} else {
    echo "  Public directory tidak ditemukan\n";
}

echo "\n=== SELESAI ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n"; 