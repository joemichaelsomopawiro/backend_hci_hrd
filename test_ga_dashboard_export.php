<?php
/**
 * Test GA Dashboard Export
 * 
 * File ini untuk menguji endpoint export Excel GA Dashboard
 * 
 * Cara menggunakan:
 * 1. Pastikan server Laravel berjalan di http://127.0.0.1:8000
 * 2. Jalankan file ini: php test_ga_dashboard_export.php
 * 3. Cek hasil di browser atau download file Excel
 */

// Konfigurasi
$baseUrl = 'http://127.0.0.1:8000/api';
$year = date('Y'); // Tahun saat ini
$allData = true; // Include data testing

echo "🧪 Testing GA Dashboard Export\n";
echo "==============================\n";
echo "Base URL: {$baseUrl}\n";
echo "Year: {$year}\n";
echo "Include all data: " . ($allData ? 'Yes' : 'No') . "\n\n";

// Test 1: Export Worship Attendance
echo "📊 Test 1: Export Worship Attendance\n";
echo "------------------------------------\n";

$url = "{$baseUrl}/ga-dashboard/export-worship-attendance?year={$year}&all=" . ($allData ? '1' : '0');

echo "URL: {$url}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "HTTP Code: {$httpCode}\n";

if ($httpCode === 200) {
    echo "✅ Success!\n";
    
    // Parse response
    $data = json_decode($body, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        echo "📁 File: " . $data['data']['filename'] . "\n";
        echo "🔗 Download URL: " . $data['data']['download_url'] . "\n";
        echo "👥 Total Employees: " . $data['data']['total_employees'] . "\n";
        echo "📅 Total Days: " . $data['data']['total_days'] . "\n";
        echo "📋 Format: " . $data['data']['format'] . "\n";
        
        // Coba download file
        echo "\n📥 Downloading file...\n";
        $downloadUrl = $data['data']['download_url'];
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        
        $fileContent = curl_exec($ch2);
        $downloadHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        if ($downloadHttpCode === 200 && !empty($fileContent)) {
            $localFile = "worship_attendance_{$year}.xls";
            file_put_contents($localFile, $fileContent);
            echo "✅ File downloaded successfully: {$localFile}\n";
            echo "📏 File size: " . number_format(strlen($fileContent)) . " bytes\n";
        } else {
            echo "❌ Failed to download file (HTTP: {$downloadHttpCode})\n";
        }
        
    } else {
        echo "❌ Response error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} else {
    echo "❌ Failed (HTTP: {$httpCode})\n";
    echo "Response: {$body}\n";
}

echo "\n";

// Test 2: Export Leave Requests
echo "📋 Test 2: Export Leave Requests\n";
echo "--------------------------------\n";

$url2 = "{$baseUrl}/ga-dashboard/export-leave-requests?year={$year}&all=" . ($allData ? '1' : '0');

echo "URL: {$url2}\n";

$ch3 = curl_init();
curl_setopt($ch3, CURLOPT_URL, $url2);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_HEADER, true);
curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch3, CURLOPT_TIMEOUT, 30);

$response2 = curl_exec($ch3);
$httpCode2 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$headerSize2 = curl_getinfo($ch3, CURLINFO_HEADER_SIZE);
$headers2 = substr($response2, 0, $headerSize2);
$body2 = substr($response2, $headerSize2);

curl_close($ch3);

echo "HTTP Code: {$httpCode2}\n";

if ($httpCode2 === 200) {
    echo "✅ Success!\n";
    
    // Parse response
    $data2 = json_decode($body2, true);
    
    if ($data2 && isset($data2['success']) && $data2['success']) {
        echo "📁 File: " . $data2['data']['filename'] . "\n";
        echo "🔗 Download URL: " . $data2['data']['download_url'] . "\n";
        echo "📋 Format: " . $data2['data']['format'] . "\n";
        
        // Coba download file
        echo "\n📥 Downloading file...\n";
        $downloadUrl2 = $data2['data']['download_url'];
        
        $ch4 = curl_init();
        curl_setopt($ch4, CURLOPT_URL, $downloadUrl2);
        curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch4, CURLOPT_FOLLOWLOCATION, true);
        
        $fileContent2 = curl_exec($ch4);
        $downloadHttpCode2 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
        curl_close($ch4);
        
        if ($downloadHttpCode2 === 200 && !empty($fileContent2)) {
            $localFile2 = "leave_requests_{$year}.xls";
            file_put_contents($localFile2, $fileContent2);
            echo "✅ File downloaded successfully: {$localFile2}\n";
            echo "📏 File size: " . number_format(strlen($fileContent2)) . " bytes\n";
        } else {
            echo "❌ Failed to download file (HTTP: {$downloadHttpCode2})\n";
        }
        
    } else {
        echo "❌ Response error: " . ($data2['message'] ?? 'Unknown error') . "\n";
    }
    
} else {
    echo "❌ Failed (HTTP: {$httpCode2})\n";
    echo "Response: {$body2}\n";
}

echo "\n";
echo "🏁 Testing completed!\n";
echo "====================\n";
echo "Check the generated Excel files in the current directory.\n";
echo "You can open them with Excel or any spreadsheet application.\n"; 