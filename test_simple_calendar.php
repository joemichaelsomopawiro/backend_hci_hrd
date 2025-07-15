<?php
/**
 * Script Testing Sederhana untuk Debug Error 500
 */

echo "Testing koneksi ke Laravel...\n";

$baseUrl = 'http://localhost:8000/api';

// Test endpoint paling sederhana
$url = $baseUrl . '/calendar/check?date=2025-01-01';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Error: $error\n";
echo "Response: $response\n";

if ($httpCode === 500) {
    echo "\nError 500 detected. Checking if Laravel server is running...\n";
    
    // Test if server is running
    $testUrl = 'http://localhost:8000';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $serverCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Server test - HTTP Code: $serverCode\n";
    
    if ($serverCode === 0) {
        echo "❌ Laravel server tidak berjalan. Jalankan: php artisan serve\n";
    } else {
        echo "✅ Laravel server berjalan. Masalah ada di kode aplikasi.\n";
    }
} 