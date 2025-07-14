<?php
// test_calendar_hr_access.php
// Script untuk test akses HR ke calendar API

$baseUrl = 'http://localhost:8000/api';
$hrToken = 'YOUR_HR_TOKEN'; // Ganti dengan token HR yang valid

function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $hrToken;
    
    $url = $baseUrl . $endpoint;
    $headers = [
        'Authorization: Bearer ' . $hrToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "=== Testing HR Access to Calendar API ===\n\n";

// Test 1: Get Calendar Data (should work for all users)
echo "1. Testing Get Calendar Data...\n";
$response = makeRequest('GET', '/calendar/data?year=2024&month=8');
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Add Holiday (HR only - should work now)
echo "2. Testing Add Holiday (HR Only)...\n";
$holidayData = [
    'date' => '2024-12-25',
    'name' => 'Libur Natal Test',
    'description' => 'Libur Natal untuk testing',
    'type' => 'custom'
];
$response = makeRequest('POST', '/calendar', $holidayData);
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Check if holiday was added
echo "3. Testing Check Holiday...\n";
$response = makeRequest('GET', '/calendar/check?date=2024-12-25');
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== Test Complete ===\n";
echo "If status 200/201 for all tests, HR access is working correctly!\n";
?> 