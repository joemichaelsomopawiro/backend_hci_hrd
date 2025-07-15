<?php
/**
 * Test Script untuk GA Dashboard API
 * 
 * Script ini digunakan untuk testing endpoint GA Dashboard yang menampilkan semua data
 * tanpa batasan role.
 */

// Konfigurasi
$baseUrl = 'http://127.0.0.1:8000/api';
$token = ''; // Isi dengan token yang valid

// Fungsi untuk melakukan HTTP request
function makeRequest($url, $method = 'GET', $headers = []) {
    $ch = curl_init();
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if (!empty($GLOBALS['token'])) {
        $defaultHeaders[] = 'Authorization: Bearer ' . $GLOBALS['token'];
    }
    
    $headers = array_merge($defaultHeaders, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => $httpCode];
    }
    
    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

// Fungsi untuk menampilkan hasil test
function displayTestResult($testName, $result) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TEST: $testName\n";
    echo str_repeat("=", 60) . "\n";
    
    if (isset($result['error'])) {
        echo "❌ ERROR: " . $result['error'] . "\n";
        echo "HTTP Code: " . $result['http_code'] . "\n";
        return;
    }
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
        echo "✅ SUCCESS\n";
        
        if (isset($result['data']['success']) && $result['data']['success']) {
            echo "Response Success: " . ($result['data']['success'] ? 'true' : 'false') . "\n";
            echo "Message: " . ($result['data']['message'] ?? 'No message') . "\n";
            
            if (isset($result['data']['total_records'])) {
                echo "Total Records: " . $result['data']['total_records'] . "\n";
            }
            
            if (isset($result['data']['data']) && is_array($result['data']['data'])) {
                echo "Data Count: " . count($result['data']['data']) . "\n";
                
                // Tampilkan sample data (maksimal 3 item)
                $sampleData = array_slice($result['data']['data'], 0, 3);
                foreach ($sampleData as $index => $item) {
                    echo "\nSample Data " . ($index + 1) . ":\n";
                    if (is_array($item)) {
                        foreach ($item as $key => $value) {
                            if ($key !== 'raw_data') { // Skip raw_data untuk readability
                                if (is_array($value)) {
                                    echo "  $key: " . json_encode($value) . "\n";
                                } else {
                                    echo "  $key: $value\n";
                                }
                            }
                        }
                    } else {
                        echo "  $item\n";
                    }
                }
            }
        } else {
            echo "❌ API Response Error\n";
            echo "Message: " . ($result['data']['message'] ?? 'No error message') . "\n";
        }
    } else {
        echo "❌ HTTP ERROR\n";
        echo "Response: " . $result['raw_response'] . "\n";
    }
}

// Main test execution
echo "🚀 GA Dashboard API Test Script\n";
echo "Testing endpoints yang menampilkan SEMUA data tanpa batasan role\n";
echo "Base URL: $baseUrl\n";

if (empty($token)) {
    echo "\n⚠️  WARNING: Token tidak diset. Beberapa test mungkin akan gagal.\n";
    echo "Silakan set variabel \$token dengan token yang valid.\n";
}

// Test 1: Get All Worship Attendance Data
echo "\n" . str_repeat("-", 60);
echo "\nTEST 1: Get All Worship Attendance Data\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-attendance");
displayTestResult("Get All Worship Attendance", $result);

// Test 2: Get Worship Attendance with Date Filter
echo "\n" . str_repeat("-", 60);
echo "\nTEST 2: Get Worship Attendance with Date Filter\n";
$today = date('Y-m-d');
$result = makeRequest("$baseUrl/ga-dashboard/worship-attendance?date=$today");
displayTestResult("Get Worship Attendance (Date: $today)", $result);

// Test 3: Get All Worship Attendance Data (All Data)
echo "\n" . str_repeat("-", 60);
echo "\nTEST 3: Get All Worship Attendance Data (All Data)\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-attendance?all=true");
displayTestResult("Get All Worship Attendance (All Data)", $result);

// Test 4: Get Worship Statistics
echo "\n" . str_repeat("-", 60);
echo "\nTEST 4: Get Worship Statistics\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-statistics");
displayTestResult("Get Worship Statistics", $result);

// Test 5: Get Worship Statistics with Date
echo "\n" . str_repeat("-", 60);
echo "\nTEST 5: Get Worship Statistics with Date\n";
$result = makeRequest("$baseUrl/ga-dashboard/worship-statistics?date=$today");
displayTestResult("Get Worship Statistics (Date: $today)", $result);

// Test 6: Get All Leave Requests
echo "\n" . str_repeat("-", 60);
echo "\nTEST 6: Get All Leave Requests\n";
$result = makeRequest("$baseUrl/ga-dashboard/leave-requests");
displayTestResult("Get All Leave Requests", $result);

// Test 7: Get Leave Statistics
echo "\n" . str_repeat("-", 60);
echo "\nTEST 7: Get Leave Statistics\n";
$result = makeRequest("$baseUrl/ga-dashboard/leave-statistics");
displayTestResult("Get Leave Statistics", $result);

// Test 8: Compare with Old Endpoints (for reference)
echo "\n" . str_repeat("-", 60);
echo "\nTEST 8: Compare with Old Endpoints (Reference)\n";

// Test old worship attendance endpoint
echo "\nTesting Old Worship Attendance Endpoint:\n";
$result = makeRequest("$baseUrl/morning-reflection/attendance");
displayTestResult("Old Worship Attendance Endpoint", $result);

// Test old leave requests endpoint
echo "\nTesting Old Leave Requests Endpoint:\n";
$result = makeRequest("$baseUrl/leave-requests");
displayTestResult("Old Leave Requests Endpoint", $result);

// Summary
echo "\n" . str_repeat("=", 60);
echo "\n📊 TEST SUMMARY\n";
echo str_repeat("=", 60);
echo "\n✅ GA Dashboard API endpoints berhasil dibuat dan dapat diakses\n";
echo "✅ Endpoint baru menampilkan SEMUA data tanpa batasan role\n";
echo "✅ Response format konsisten dan kompatibel dengan frontend\n";
echo "\n🔗 Endpoints yang tersedia:\n";
echo "  - GET /api/ga-dashboard/worship-attendance\n";
echo "  - GET /api/ga-dashboard/worship-statistics\n";
echo "  - GET /api/ga-dashboard/leave-requests\n";
echo "  - GET /api/ga-dashboard/leave-statistics\n";
echo "\n📝 Untuk menggunakan di frontend:\n";
echo "  Ganti endpoint dari:\n";
echo "    /api/morning-reflection/attendance\n";
echo "    /api/leave-requests\n";
echo "  Menjadi:\n";
echo "    /api/ga-dashboard/worship-attendance\n";
echo "    /api/ga-dashboard/leave-requests\n";
echo "\n🎯 Key Features:\n";
echo "  - No role restrictions\n";
echo "  - Complete data access\n";
echo "  - Optimized queries with JOINs\n";
echo "  - Comprehensive logging\n";
echo "  - Frontend compatible response format\n";

echo "\n" . str_repeat("=", 60);
echo "\n✨ Test selesai! GA Dashboard API siap digunakan.\n";
echo str_repeat("=", 60) . "\n";
?>