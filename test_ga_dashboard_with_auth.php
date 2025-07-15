<?php

// Test GA Dashboard dengan authentication
$baseUrl = 'http://127.0.0.1:8000/api';

// Credentials untuk login (ganti dengan credentials yang valid)
$credentials = [
    'email' => 'ga@hci.com', // Ganti dengan email GA yang valid
    'password' => 'password'  // Ganti dengan password yang valid
];

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        $token ? "Authorization: Bearer $token" : ''
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

function login($credentials) {
    global $baseUrl;
    
    echo "Logging in with email: {$credentials['email']}\n";
    
    $result = makeRequest("$baseUrl/login", 'POST', $credentials);
    
    if ($result['status_code'] === 200 && isset($result['response']['token'])) {
        echo "✅ Login successful\n";
        return $result['response']['token'];
    } else {
        echo "❌ Login failed\n";
        echo "Status: " . $result['status_code'] . "\n";
        echo "Response: " . $result['raw_response'] . "\n";
        return null;
    }
}

function testEndpoint($url, $token, $testName) {
    echo "\n=== $testName ===\n";
    $result = makeRequest($url, 'GET', null, $token);
    
    echo "Status Code: " . $result['status_code'] . "\n";
    
    if ($result['status_code'] === 200) {
        echo "✅ SUCCESS\n";
        if (isset($result['response']['success']) && $result['response']['success']) {
            echo "Message: " . ($result['response']['message'] ?? 'No message') . "\n";
            echo "Total Records: " . ($result['response']['total_records'] ?? 'N/A') . "\n";
            
            // Show sample data if available
            if (isset($result['response']['data']) && is_array($result['response']['data']) && count($result['response']['data']) > 0) {
                echo "Sample Data: " . json_encode(array_slice($result['response']['data'], 0, 1), JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "❌ API returned success=false\n";
            echo "Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ FAILED\n";
        echo "Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
    }
}

echo "=== GA Dashboard Authentication Test ===\n";

// Step 1: Login
$token = login($credentials);

if (!$token) {
    echo "\n❌ Cannot proceed without valid token\n";
    echo "Please check your credentials or create a GA user first\n";
    exit(1);
}

// Step 2: Test endpoints
$today = date('Y-m-d');

// Test 1: Worship Attendance (Today)
testEndpoint("$baseUrl/ga-dashboard/worship-attendance?date=$today", $token, "Worship Attendance (Today)");

// Test 2: Worship Attendance (All Data)
testEndpoint("$baseUrl/ga-dashboard/worship-attendance?all=true", $token, "Worship Attendance (All Data)");

// Test 3: Worship Statistics
testEndpoint("$baseUrl/ga-dashboard/worship-statistics", $token, "Worship Statistics");

// Test 4: Leave Requests
testEndpoint("$baseUrl/ga-dashboard/leave-requests", $token, "Leave Requests");

// Test 5: Leave Statistics
testEndpoint("$baseUrl/ga-dashboard/leave-statistics", $token, "Leave Statistics");

echo "\n=== Test Completed ===\n";
echo "Jika semua test menunjukkan ✅ SUCCESS, berarti endpoint sudah berfungsi dengan baik!\n"; 