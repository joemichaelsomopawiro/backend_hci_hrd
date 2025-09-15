<?php

/**
 * Test Personal Office Attendance API Endpoint
 * 
 * File ini untuk testing endpoint personal office attendance yang baru dibuat
 * Jalankan dengan: php test_personal_office_attendance.php
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

echo "🧪 Testing Personal Office Attendance API Endpoint\n";
echo "==================================================\n\n";

// Test 1: Get Personal Office Attendance
echo "1. Testing GET /api/personal/office-attendance\n";
echo "-----------------------------------------------\n";

$employeeId = 1; // Employee ID yang ada di database
$url = $baseUrl . '/personal/office-attendance?employee_id=' . $employeeId;

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

// Test 2: Get Personal Office Attendance with date range
echo "2. Testing GET /api/personal/office-attendance with date range\n";
echo "---------------------------------------------------------------\n";

$startDate = '2025-01-01';
$endDate = '2025-01-31';
$url = $baseUrl . '/personal/office-attendance?employee_id=' . $employeeId . '&start_date=' . $startDate . '&end_date=' . $endDate;

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
echo "3. Testing GET /api/personal/office-attendance without employee_id\n";
echo "-------------------------------------------------------------------\n";

$url = $baseUrl . '/personal/office-attendance';

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
echo "4. Testing GET /api/personal/office-attendance with invalid employee_id\n";
echo "-----------------------------------------------------------------------\n";

$invalidEmployeeId = 99999; // ID yang tidak ada
$url = $baseUrl . '/personal/office-attendance?employee_id=' . $invalidEmployeeId;

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

echo "✅ Testing completed!\n";
echo "==================================================\n";
echo "Expected Results:\n";
echo "- Test 1 & 2: Should return 200 with attendance data\n";
echo "- Test 3: Should return 422 (validation error)\n";
echo "- Test 4: Should return 404 (employee not found)\n";
echo "\n";
echo "If all tests pass, the endpoint is working correctly! 🎉\n"; 