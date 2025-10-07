<?php

/**
 * Script Testing untuk Program Management Routes
 * 
 * Usage: php test_program_management.php
 * 
 * Script ini akan test semua endpoint Program Management
 * untuk memastikan tidak ada autologout dan semua route berfungsi.
 */

$baseUrl = 'http://localhost:8000/api';

echo "===========================================\n";
echo "   PROGRAM MANAGEMENT ROUTES TEST\n";
echo "===========================================\n\n";

// Function helper untuk HTTP request
function testEndpoint($method, $url, $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Set method
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    // Set headers
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// Test 1: Get All Programs
echo "Test 1: GET /programs (Get All Programs)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/programs");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
    echo "Error: {$result['error']}\n";
}
echo "\n";

// Test 2: Get All Teams
echo "Test 2: GET /teams (Get All Teams)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/teams");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 3: Get All Episodes
echo "Test 3: GET /episodes (Get All Episodes)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/episodes");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 4: Get All Schedules
echo "Test 4: GET /schedules (Get All Schedules)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/schedules");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 5: Get All Media Files
echo "Test 5: GET /media-files (Get All Media Files)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/media-files");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 6: Get All Production Equipment
echo "Test 6: GET /production-equipment (Get All Production Equipment)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/production-equipment");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 7: Get All Program Notifications
echo "Test 7: GET /program-notifications (Get All Notifications)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/program-notifications");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    if (isset($data['data'])) {
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 8: Get Unread Count
echo "Test 8: GET /program-notifications/unread-count (Get Unread Count)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/program-notifications/unread-count");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - Route accessible\n";
    $data = json_decode($result['response'], true);
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 9: Get All Users
echo "Test 9: GET /users (Get All Users)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/users");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200 || $result['code'] == 401) {
    if ($result['code'] == 200) {
        echo "✅ SUCCESS - Route accessible without auth\n";
    } else {
        echo "⚠️  REQUIRES AUTH - Route requires authentication (expected)\n";
    }
    $data = json_decode($result['response'], true);
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ FAILED - Route not accessible\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

// Test 10: Test CORS
echo "Test 10: GET /test-cors (Test CORS)\n";
echo "-------------------------------------------\n";
$result = testEndpoint('GET', "$baseUrl/test-cors");
echo "Status Code: {$result['code']}\n";
if ($result['code'] == 200) {
    echo "✅ SUCCESS - CORS working\n";
    $data = json_decode($result['response'], true);
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ FAILED - CORS not working\n";
    echo "Response: {$result['response']}\n";
}
echo "\n";

echo "===========================================\n";
echo "   TEST COMPLETED\n";
echo "===========================================\n\n";

echo "📋 Summary:\n";
echo "- Jika semua test menunjukkan ✅ SUCCESS, backend sudah siap!\n";
echo "- Jika ada ❌ FAILED, check error message dan laravel.log\n";
echo "- Jika ada ⚠️ REQUIRES AUTH, itu normal untuk route tertentu\n\n";

echo "💡 Tips:\n";
echo "1. Pastikan server Laravel sudah running (php artisan serve)\n";
echo "2. Check file: storage/logs/laravel.log untuk detail error\n";
echo "3. Test dari frontend Anda untuk memastikan tidak ada autologout\n\n";

echo "🔗 Dokumentasi lengkap: PROGRAM_MANAGEMENT_ROUTES_FIX.md\n\n";

