<?php

/**
 * Test Personal Worship API Endpoints
 * 
 * File ini untuk testing endpoint personal worship yang baru dibuat
 * Jalankan dengan: php test_personal_worship_api.php
 */

// Konfigurasi
$baseUrl = 'http://127.0.0.1:8000/api';
$token = 'YOUR_TOKEN_HERE'; // Ganti dengan token yang valid

// Headers
$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
];

echo "🧪 Testing Personal Worship API Endpoints\n";
echo "==========================================\n\n";

// Test 1: Get Personal Worship Attendance
echo "1. Testing GET /api/personal/worship-attendance\n";
echo "-----------------------------------------------\n";

$employeeId = 1; // Ganti dengan employee ID yang valid
$url = $baseUrl . '/personal/worship-attendance?employee_id=' . $employeeId;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Test 2: Get Combined Attendance
echo "2. Testing GET /api/personal/combined-attendance\n";
echo "------------------------------------------------\n";

$startDate = '2025-01-01';
$endDate = '2025-01-31';
$url = $baseUrl . '/personal/combined-attendance?employee_id=' . $employeeId . '&start_date=' . $startDate . '&end_date=' . $endDate;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Test 3: Test without employee_id (should return 422)
echo "3. Testing GET /api/personal/worship-attendance without employee_id\n";
echo "-------------------------------------------------------------------\n";

$url = $baseUrl . '/personal/worship-attendance';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Test 4: Test with invalid employee_id (should return 404)
echo "4. Testing GET /api/personal/worship-attendance with invalid employee_id\n";
echo "------------------------------------------------------------------------\n";

$invalidEmployeeId = 99999;
$url = $baseUrl . '/personal/worship-attendance?employee_id=' . $invalidEmployeeId;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Test 5: Test without authentication (should return 401)
echo "5. Testing GET /api/personal/worship-attendance without authentication\n";
echo "---------------------------------------------------------------------\n";

$url = $baseUrl . '/personal/worship-attendance?employee_id=' . $employeeId;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

echo "✅ Testing completed!\n";
echo "\n📋 Summary:\n";
echo "- Test 1: Personal worship attendance (should return 200)\n";
echo "- Test 2: Combined attendance (should return 200)\n";
echo "- Test 3: Missing employee_id (should return 422)\n";
echo "- Test 4: Invalid employee_id (should return 404)\n";
echo "- Test 5: No authentication (should return 401)\n";

echo "\n🔧 Instructions:\n";
echo "1. Ganti 'YOUR_TOKEN_HERE' dengan token yang valid\n";
echo "2. Ganti employee_id dengan ID employee yang ada di database\n";
echo "3. Pastikan server Laravel berjalan di http://127.0.0.1:8000\n";
echo "4. Jalankan: php test_personal_worship_api.php\n"; 