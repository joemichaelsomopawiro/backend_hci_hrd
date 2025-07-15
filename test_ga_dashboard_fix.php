<?php

// Test GA Dashboard Endpoint Fix
$baseUrl = 'http://127.0.0.1:8000/api';

// Token untuk testing (ganti dengan token yang valid)
$token = 'YOUR_TOKEN_HERE'; // Ganti dengan token yang valid

function makeRequest($url, $method = 'GET', $data = null) {
    global $token;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

function displayTestResult($testName, $result) {
    echo "\n=== $testName ===\n";
    echo "Status Code: " . $result['status_code'] . "\n";
    
    if ($result['status_code'] === 200) {
        echo "✅ SUCCESS\n";
        if (isset($result['response']['success']) && $result['response']['success']) {
            echo "Message: " . ($result['response']['message'] ?? 'No message') . "\n";
            echo "Total Records: " . ($result['response']['total_records'] ?? 'N/A') . "\n";
        } else {
            echo "❌ API returned success=false\n";
            echo "Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ FAILED\n";
        echo "Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
        if (isset($result['response']['error'])) {
            echo "Details: " . $result['response']['error'] . "\n";
        }
    }
}

echo "=== GA Dashboard Endpoint Fix Test ===\n";
echo "Testing fixed endpoints...\n";

// Test 1: Get Worship Attendance (Today)
echo "\n" . str_repeat("-", 60);
echo "\nTEST 1: Get Worship Attendance (Today)\n";
$today = date('Y-m-d');
$result = makeRequest("$baseUrl/ga-dashboard/worship-attendance?date=$today");
displayTestResult("Get Worship Attendance (Date: $today)", $result);

// Test 2: Get All Worship Attendance Data
echo "\n" . str_repeat("-", 60);
echo "\nTEST 2: Get All Worship Attendance Data\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-attendance?all=true");
displayTestResult("Get All Worship Attendance (All Data)", $result);

// Test 3: Get Worship Statistics
echo "\n" . str_repeat("-", 60);
echo "\nTEST 3: Get Worship Statistics\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-statistics");
displayTestResult("Get Worship Statistics", $result);

// Test 4: Get Leave Requests
echo "\n" . str_repeat("-", 60);
echo "\nTEST 4: Get Leave Requests\n";
$result = makeRequest("$baseUrl/ga-dashboard/leave-requests");
displayTestResult("Get Leave Requests", $result);

// Test 5: Get Leave Statistics
echo "\n" . str_repeat("-", 60);
echo "\nTEST 5: Get Leave Statistics\n";
$result = makeRequest("$baseUrl/ga-dashboard/leave-statistics");
displayTestResult("Get Leave Statistics", $result);

echo "\n" . str_repeat("=", 60);
echo "\nTest completed!\n";
echo "If all tests show ✅ SUCCESS, the fix is working correctly.\n";
echo "If any test shows ❌ FAILED, check the error message for further debugging.\n";