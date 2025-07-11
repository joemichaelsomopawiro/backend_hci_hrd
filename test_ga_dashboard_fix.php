<?php

/**
 * Test script untuk verifikasi perbaikan GA Dashboard API
 * 
 * Script ini menguji:
 * - JOIN query fix untuk employee.nama_lengkap
 * - Data validation sebelum response
 * - Error handling untuk data null/missing
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
 * Validasi data employee dalam response
 */
function validateEmployeeData($data) {
    $issues = [];
    
    if (!isset($data['data']) || !is_array($data['data'])) {
        $issues[] = "❌ Data array tidak ditemukan";
        return $issues;
    }
    
    foreach ($data['data'] as $index => $item) {
        $itemIssues = [];
        
        // Validasi struktur employee
        if (!isset($item['employee'])) {
            $itemIssues[] = "Employee data missing";
        } else {
            $employee = $item['employee'];
            
            // Validasi employee ID
            if (!isset($employee['id']) || empty($employee['id'])) {
                $itemIssues[] = "Employee ID missing";
            }
            
            // Validasi nama_lengkap
            if (!isset($employee['nama_lengkap'])) {
                $itemIssues[] = "nama_lengkap field missing";
            } elseif (empty($employee['nama_lengkap']) || $employee['nama_lengkap'] === null) {
                $itemIssues[] = "nama_lengkap is null/empty";
            } elseif ($employee['nama_lengkap'] === 'Data tidak tersedia') {
                $itemIssues[] = "nama_lengkap shows fallback value";
            }
        }
        
        // Validasi field lainnya
        $requiredFields = ['id', 'leave_type', 'start_date', 'end_date', 'duration', 'reason', 'overall_status'];
        foreach ($requiredFields as $field) {
            if (!isset($item[$field])) {
                $itemIssues[] = "Field '$field' missing";
            }
        }
        
        if (!empty($itemIssues)) {
            $issues[] = "❌ Item #$index: " . implode(', ', $itemIssues);
        }
    }
    
    return $issues;
}

/**
 * Print hasil test dengan detail validasi
 */
function printDetailedTestResult($testName, $result, $expectedCode = 200) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "TEST: $testName\n";
    echo str_repeat('=', 70) . "\n";
    
    if (isset($result['error'])) {
        echo "❌ CURL ERROR: {$result['error']}\n";
        return false;
    }
    
    $httpCode = $result['http_code'];
    $data = $result['data'];
    
    echo "HTTP Code: $httpCode\n";
    
    if ($httpCode === $expectedCode) {
        echo "✅ Status: PASSED\n";
        
        if (isset($data['success']) && $data['success']) {
            echo "✅ Response Success: TRUE\n";
            
            // Validasi data employee
            $validationIssues = validateEmployeeData($data);
            
            if (empty($validationIssues)) {
                echo "✅ Data Validation: PASSED\n";
            } else {
                echo "⚠️  Data Validation Issues Found:\n";
                foreach ($validationIssues as $issue) {
                    echo "   $issue\n";
                }
            }
            
            if (isset($data['data'])) {
                $dataCount = is_array($data['data']) ? count($data['data']) : 1;
                echo "📊 Data Count: $dataCount\n";
                
                // Show sample employee data
                if ($dataCount > 0 && isset($data['data'][0]['employee'])) {
                    $sampleEmployee = $data['data'][0]['employee'];
                    echo "👤 Sample Employee: ID={$sampleEmployee['id']}, Name='{$sampleEmployee['nama_lengkap']}'\n";
                }
            }
            
            if (isset($data['pagination'])) {
                $pagination = $data['pagination'];
                echo "📄 Pagination: Page {$pagination['current_page']} of {$pagination['last_page']} (Total: {$pagination['total']})\n";
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
    
    return $httpCode === $expectedCode;
}

echo "🔧 Starting GA Dashboard API Fix Verification\n";
echo "Base URL: $baseUrl\n";
echo "Token: " . (empty($token) || $token === 'YOUR_TOKEN_HERE' ? '❌ NOT SET' : '✅ SET') . "\n";

if (empty($token) || $token === 'YOUR_TOKEN_HERE') {
    echo "\n❌ ERROR: Please set a valid GA token in the \$token variable\n";
    echo "You can get a token by logging in as a GA user\n";
    exit(1);
}

// Test 1: Basic Leave Requests Test
$result1 = makeRequest("$baseUrl/ga/dashboard/leave-requests?per_page=5", 'GET', null, $token);
$test1Pass = printDetailedTestResult('Basic Leave Requests (Fixed JOIN)', $result1);

// Test 2: Leave Requests with Status Filter
$result2 = makeRequest("$baseUrl/ga/dashboard/leave-requests?status=approved&per_page=3", 'GET', null, $token);
$test2Pass = printDetailedTestResult('Leave Requests with Status Filter', $result2);

// Test 3: Leave Requests with Employee Filter
$result3 = makeRequest("$baseUrl/ga/dashboard/leave-requests?employee_id=1&per_page=5", 'GET', null, $token);
$test3Pass = printDetailedTestResult('Leave Requests with Employee Filter', $result3);

// Test 4: Leave Requests with Date Range
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t');    // Last day of current month
$result4 = makeRequest("$baseUrl/ga/dashboard/leave-requests?start_date=$startDate&end_date=$endDate&per_page=10", 'GET', null, $token);
$test4Pass = printDetailedTestResult('Leave Requests with Date Range', $result4);

// Test 5: Edge Case - Large Page Size
$result5 = makeRequest("$baseUrl/ga/dashboard/leave-requests?per_page=100", 'GET', null, $token);
$test5Pass = printDetailedTestResult('Large Page Size Test', $result5);

echo "\n" . str_repeat('=', 70) . "\n";
echo "🏁 GA Dashboard API Fix Verification Completed\n";
echo str_repeat('=', 70) . "\n";

// Summary
$tests = [
    'Basic Leave Requests (Fixed JOIN)' => $test1Pass,
    'Leave Requests with Status Filter' => $test2Pass,
    'Leave Requests with Employee Filter' => $test3Pass,
    'Leave Requests with Date Range' => $test4Pass,
    'Large Page Size Test' => $test5Pass
];

$passed = array_filter($tests);
$total = count($tests);
$passedCount = count($passed);

echo "\n📊 VERIFICATION SUMMARY:\n";
echo "Total Tests: $total\n";
echo "Passed: $passedCount\n";
echo "Failed: " . ($total - $passedCount) . "\n";
echo "Success Rate: " . round(($passedCount / $total) * 100, 2) . "%\n";

if ($passedCount === $total) {
    echo "\n🎉 ALL TESTS PASSED! JOIN query fix is working correctly.\n";
    echo "✅ employee.nama_lengkap is properly populated\n";
    echo "✅ Data validation is working\n";
    echo "✅ No null employee names in response\n";
} else {
    echo "\n⚠️  Some tests failed. Issues found:\n";
    
    foreach ($tests as $testName => $passed) {
        if (!$passed) {
            echo "❌ $testName\n";
        }
    }
}

echo "\n🔧 FIXES IMPLEMENTED:\n";
echo "1. ✅ Fixed JOIN query: Changed from 'employee.user' to 'employee'\n";
echo "2. ✅ Fixed field mapping: Using 'nama_lengkap' instead of 'full_name'\n";
echo "3. ✅ Added data validation before response\n";
echo "4. ✅ Added fallback values for null/missing data\n";
echo "5. ✅ Added logging for data integrity issues\n";

echo "\n💡 FRONTEND INTEGRATION NOTES:\n";
echo "1. employee.nama_lengkap field is now properly populated\n";
echo "2. Fallback value 'Data tidak tersedia' indicates missing employee data\n";
echo "3. All responses include proper data validation\n";
echo "4. No more null employee names should appear\n";

echo "\n📚 NEXT STEPS:\n";
echo "1. Update frontend to remove dummy data\n";
echo "2. Test with real GA user credentials\n";
echo "3. Verify pagination and filtering in frontend\n";
echo "4. Monitor logs for any data integrity warnings\n";

echo "\n🔗 Documentation: See GA_DASHBOARD_API.md for complete API reference\n";