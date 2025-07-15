<?php

/**
 * Test Export Worship Attendance Excel
 * 
 * File ini untuk menguji endpoint export Excel data absensi ibadah
 */

// Include autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Base URL
$baseUrl = 'http://127.0.0.1:8000';

// Test credentials (ganti dengan user yang valid)
$email = 'admin@example.com';
$password = 'password';

echo "=== TEST EXPORT WORSHIP ATTENDANCE ===\n\n";

// Step 1: Login untuk mendapatkan token
echo "1. Login untuk mendapatkan token...\n";
$loginData = [
    'email' => $email,
    'password' => $password
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Login gagal. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$loginResult = json_decode($response, true);
if (!$loginResult['success']) {
    echo "❌ Login gagal: " . $loginResult['message'] . "\n";
    exit(1);
}

$token = $loginResult['data']['token'];
echo "✅ Login berhasil. Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Test export worship attendance untuk tahun 2025
echo "2. Test export worship attendance untuk tahun 2025...\n";
$exportUrl = $baseUrl . '/api/ga-dashboard/export-worship-attendance?year=2025&all=true';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $exportUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";

if ($httpCode === 200 && strpos($contentType, 'spreadsheet') !== false) {
    // Simpan file Excel
    $filename = "Data_Absensi_Ibadah_2025_" . date('Y-m-d_H-i-s') . ".xlsx";
    file_put_contents($filename, $response);
    echo "✅ Export berhasil! File disimpan sebagai: $filename\n";
    echo "File size: " . number_format(strlen($response)) . " bytes\n";
} else {
    echo "❌ Export gagal\n";
    if ($httpCode !== 200) {
        echo "Response: $response\n";
    }
}

echo "\n";

// Step 3: Test export leave requests untuk tahun 2025
echo "3. Test export leave requests untuk tahun 2025...\n";
$exportUrl = $baseUrl . '/api/ga-dashboard/export-leave-requests?year=2025&all=true';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $exportUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";

if ($httpCode === 200 && strpos($contentType, 'spreadsheet') !== false) {
    // Simpan file Excel
    $filename = "Data_Cuti_2025_" . date('Y-m-d_H-i-s') . ".xlsx";
    file_put_contents($filename, $response);
    echo "✅ Export berhasil! File disimpan sebagai: $filename\n";
    echo "File size: " . number_format(strlen($response)) . " bytes\n";
} else {
    echo "❌ Export gagal\n";
    if ($httpCode !== 200) {
        echo "Response: $response\n";
    }
}

echo "\n=== TEST SELESAI ===\n";

// Step 4: Test tanpa parameter (default tahun sekarang)
echo "4. Test export worship attendance tanpa parameter (default tahun sekarang)...\n";
$exportUrl = $baseUrl . '/api/ga-dashboard/export-worship-attendance';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $exportUrl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";

if ($httpCode === 200 && strpos($contentType, 'spreadsheet') !== false) {
    // Simpan file Excel
    $currentYear = date('Y');
    $filename = "Data_Absensi_Ibadah_{$currentYear}_" . date('Y-m-d_H-i-s') . ".xlsx";
    file_put_contents($filename, $response);
    echo "✅ Export berhasil! File disimpan sebagai: $filename\n";
    echo "File size: " . number_format(strlen($response)) . " bytes\n";
} else {
    echo "❌ Export gagal\n";
    if ($httpCode !== 200) {
        echo "Response: $response\n";
    }
}

echo "\n=== SEMUA TEST SELESAI ===\n"; 