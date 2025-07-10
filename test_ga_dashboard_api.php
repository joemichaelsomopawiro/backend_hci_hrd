<?php

/**
 * Test script untuk GA Dashboard API
 * 
 * Script ini menguji endpoint-endpoint baru yang dibuat untuk GA Dashboard:
 * - GET /ga/dashboard/leave-requests
 * - GET /ga/dashboard/attendances
 * - GET /ga/dashboard/leave-statistics
 * - GET /ga/dashboard/attendance-statistics
 * - GET /ga/leaves
 */

require_once __DIR__ . '/vendor/autoload.php';

// Configuration
$baseUrl = 'http://localhost:8000/api';
$token = 'YOUR_TOKEN_HERE'; // Ganti dengan token GA yang valid

/**
 * Helper function untuk melakukan HTTP request
 */
function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            $token ? "Authorization: Bearer $token" : ''
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

/**
 * Helper function untuk print hasil test
 */
function printTestResult($testName, $result, $expectedCode = 200) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "TEST: $testName\n";
    echo str_repeat('=', 60) . "\n";
    
    if (isset($result['error'])) {
        echo "❌ ERROR: {$result['error']}\n";
        return false;
    }
    
    $httpCode = $result['http_code'];
    $data = $result['data'];
    
    echo "HTTP Code: $httpCode\n";
    
    if ($httpCode === $expectedCode) {
        echo "✅ Status: PASSED\n";
        
        if (isset($data['success']) && $data['success']) {
            echo "✅ Response Success: TRUE\n";
            
            if (isset($data['data'])) {
                $dataCount = is_array($data['data']) ? count($data['data']) : 1;
                echo "📊 Data Count: $dataCount\n";
            }
            
            if (isset($data['pagination'])) {
                $pagination = $data['pagination'];
                echo "📄 Pagination: Page {$pagination['current_page']} of {$pagination['last_page']} (Total: {$pagination['total']})\n";
            }
            
            if (isset($data['message'])) {
                echo "💬 Message: {$data['message']}\n";
            }
        } else {
            echo "❌ Response Success: FALSE\n";
            if (isset($data['message'])) {
                echo "💬 Error Message: {$data['message']}\n";
            }
        }
    } else {
        echo "❌ Status: FAILED (Expected: $expectedCode, Got: $httpCode)\n";
        
        if (isset($data['message'])) {
            echo "💬 Error Message: {$data['message']}\n";
        }
    }
    
    // Print sample data jika ada
    if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
        echo "\n📋 Sample Data (First Item):\n";
        echo json_encode($data['data'][0], JSON_PRETTY_PRINT) . "\n";
    }
    
    return $httpCode === $expectedCode;
}

echo "🚀 Starting GA Dashboard API Tests\n";
echo "Base URL: $baseUrl\n";
echo "Token: " . (empty($token) || $token === 'YOUR_TOKEN_HERE' ? '❌ NOT SET' : '✅ SET') . "\n";

if (empty($token) || $token === 'YOUR_TOKEN_HERE') {
    echo "\n❌ ERROR: Please set a valid token in the \$token variable\n";
    echo "You can get a token by logging in as a GA user\n";
    exit(1);
}

// Test 1: Get All Leave Requests
$result1 = makeRequest("$baseUrl/ga/dashboard/leave-requests", 'GET', null, $token);
printTestResult('Get All Leave Requests', $result1);

// Test 2: Get All Leave Requests with Filters
$result2 = makeRequest("$baseUrl/ga/dashboard/leave-requests?status=pending&per_page=5", 'GET', null, $token);
printTestResult('Get Leave Requests with Filters', $result2);

// Test 3: Get All Attendances
$result3 = makeRequest("$baseUrl/ga/dashboard/attendances", 'GET', null, $token);
printTestResult('Get All Attendances', $result3);

// Test 4: Get Attendances for Specific Date
$today = date('Y-m-d');
$result4 = makeRequest("$baseUrl/ga/dashboard/attendances?date=$today&per_page=10", 'GET', null, $token);
printTestResult('Get Attendances for Today', $result4);

// Test 5: Get Leave Statistics
$result5 = makeRequest("$baseUrl/ga/dashboard/leave-statistics", 'GET', null, $token);
printTestResult('Get Leave Statistics', $result5);

// Test 6: Get Attendance Statistics
$result6 = makeRequest("$baseUrl/ga/dashboard/attendance-statistics", 'GET', null, $token);
printTestResult('Get Attendance Statistics', $result6);

// Test 7: Get Leaves (Alternative Endpoint)
$result7 = makeRequest("$baseUrl/ga/leaves", 'GET', null, $token);
printTestResult('Get Leaves (Alternative)', $result7);

// Test 8: Test Unauthorized Access (without token)
$result8 = makeRequest("$baseUrl/ga/dashboard/leave-requests", 'GET', null, null);
printTestResult('Unauthorized Access Test', $result8, 401);

echo "\n" . str_repeat('=', 60) . "\n";
echo "🏁 GA Dashboard API Tests Completed\n";
echo str_repeat('=', 60) . "\n";

// Summary
$tests = [
    'Get All Leave Requests' => $result1['http_code'] === 200,
    'Get Leave Requests with Filters' => $result2['http_code'] === 200,
    'Get All Attendances' => $result3['http_code'] === 200,
    'Get Attendances for Today' => $result4['http_code'] === 200,
    'Get Leave Statistics' => $result5['http_code'] === 200,
    'Get Attendance Statistics' => $result6['http_code'] === 200,
    'Get Leaves (Alternative)' => $result7['http_code'] === 200,
    'Unauthorized Access Test' => $result8['http_code'] === 401
];

$passed = array_filter($tests);
$total = count($tests);
$passedCount = count($passed);

echo "\n📊 TEST SUMMARY:\n";
echo "Total Tests: $total\n";
echo "Passed: $passedCount\n";
echo "Failed: " . ($total - $passedCount) . "\n";
echo "Success Rate: " . round(($passedCount / $total) * 100, 2) . "%\n";

if ($passedCount === $total) {
    echo "\n🎉 ALL TESTS PASSED! GA Dashboard API is working correctly.\n";
} else {
    echo "\n⚠️  Some tests failed. Please check the implementation.\n";
    
    echo "\nFailed Tests:\n";
    foreach ($tests as $testName => $passed) {
        if (!$passed) {
            echo "❌ $testName\n";
        }
    }
}

echo "\n💡 USAGE TIPS:\n";
echo "1. Make sure you have a valid GA user token\n";
echo "2. Ensure the Laravel server is running on localhost:8000\n";
echo "3. Check that the database has sample data for testing\n";
echo "4. Verify that the GA user has proper permissions\n";

echo "\n📚 NEXT STEPS:\n";
echo "1. Test the endpoints in your frontend application\n";
echo "2. Implement proper error handling in your frontend\n";
echo "3. Add loading states and pagination controls\n";
echo "4. Test with different filter combinations\n";

echo "\n🔗 API Documentation: See GA_DASHBOARD_API.md for detailed documentation\n";