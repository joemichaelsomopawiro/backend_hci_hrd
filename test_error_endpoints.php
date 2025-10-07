<?php

// Test Error Endpoints
echo "=== TESTING ERROR ENDPOINTS ===\n\n";

$baseUrl = 'http://localhost:8000/api';

// Function untuk test API endpoint
function testEndpoint($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Test 1: Workflow States
echo "1. Testing Workflow States...\n";
$result = testEndpoint($baseUrl . '/workflow/states');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] === 200) {
    echo "✅ Workflow States API working\n";
} else {
    echo "❌ Workflow States API failed\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Test 2: Workflow Steps
echo "2. Testing Workflow Steps...\n";
$result = testEndpoint($baseUrl . '/workflow/steps');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] === 200) {
    echo "✅ Workflow Steps API working\n";
} else {
    echo "❌ Workflow Steps API failed\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Test 3: Workflow Dashboard
echo "3. Testing Workflow Dashboard...\n";
$result = testEndpoint($baseUrl . '/workflow/dashboard');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] === 200 || $result['status'] === 401) {
    echo "✅ Workflow Dashboard API working (401 is expected without auth)\n";
} else {
    echo "❌ Workflow Dashboard API failed\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Test 4: File Statistics
echo "4. Testing File Statistics...\n";
$result = testEndpoint($baseUrl . '/files/statistics');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] === 200) {
    echo "✅ File Statistics API working\n";
} else {
    echo "❌ File Statistics API failed\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

echo "=== ERROR ENDPOINT TESTING COMPLETED ===\n";

