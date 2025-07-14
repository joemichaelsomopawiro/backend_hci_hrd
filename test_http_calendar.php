<?php
// test_http_calendar.php
// Script untuk test HTTP request ke calendar endpoint

$baseUrl = 'http://localhost:8000/api';
$hrToken = 'YOUR_HR_TOKEN'; // Ganti dengan token HR yang valid

function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $hrToken;
    
    $url = $baseUrl . $endpoint;
    $headers = [
        'Authorization: Bearer ' . $hrToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'status' => 0,
            'error' => $error,
            'data' => null
        ];
    }
    
    // Split header and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    return [
        'status' => $httpCode,
        'header' => $header,
        'body' => $body,
        'data' => json_decode($body, true)
    ];
}

echo "=== Test HTTP Calendar Endpoint ===\n\n";

// Test 1: Get Calendar Data (should work)
echo "1. Testing GET /api/calendar/data...\n";
$response = makeRequest('GET', '/calendar/data?year=2024&month=12');
echo "Status: " . $response['status'] . "\n";
if ($response['status'] === 200) {
    echo "✅ GET request successful\n";
} else {
    echo "❌ GET request failed\n";
    echo "Response: " . $response['body'] . "\n";
}
echo "\n";

// Test 2: POST Calendar (HR only)
echo "2. Testing POST /api/calendar...\n";
$holidayData = [
    'date' => '2024-12-27',
    'name' => 'Libur Test HTTP',
    'description' => 'Test via HTTP request',
    'type' => 'custom'
];

$response = makeRequest('POST', '/calendar', $holidayData);
echo "Status: " . $response['status'] . "\n";

if ($response['status'] === 201 || $response['status'] === 200) {
    echo "✅ POST request successful\n";
    echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ POST request failed\n";
    echo "Response: " . $response['body'] . "\n";
}

echo "\n=== Test Complete ===\n";
echo "If you see HTML in response, it means the endpoint is not found or server error.\n";
echo "If you see JSON error, check the error message.\n";
?> 