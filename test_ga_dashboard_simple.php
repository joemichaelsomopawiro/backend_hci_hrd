<?php

// Test sederhana untuk GA Dashboard endpoint
$baseUrl = 'http://127.0.0.1:8000/api';

// Test tanpa token dulu untuk melihat apakah endpoint bisa diakses
function testEndpoint($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => $response
    ];
}

echo "=== Test GA Dashboard Endpoint ===\n";

// Test 1: Worship Attendance endpoint
echo "\n1. Testing /api/ga-dashboard/worship-attendance\n";
$result = testEndpoint("$baseUrl/ga-dashboard/worship-attendance");
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . substr($result['response'], 0, 200) . "...\n";

// Test 2: Worship Statistics endpoint
echo "\n2. Testing /api/ga-dashboard/worship-statistics\n";
$result = testEndpoint("$baseUrl/ga-dashboard/worship-statistics");
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . substr($result['response'], 0, 200) . "...\n";

// Test 3: Leave Requests endpoint
echo "\n3. Testing /api/ga-dashboard/leave-requests\n";
$result = testEndpoint("$baseUrl/ga-dashboard/leave-requests");
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . substr($result['response'], 0, 200) . "...\n";

echo "\n=== Test Completed ===\n";
echo "Jika status code 401, berarti endpoint memerlukan authentication (normal)\n";
echo "Jika status code 500, berarti ada error di backend\n";
echo "Jika status code 200, berarti endpoint berfungsi dengan baik\n"; 