<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== TESTING MORNING REFLECTION ATTENDANCE ENDPOINT FIX ===\n";

$baseUrl = 'http://localhost:8000/api';

// Test 1: Get attendance data (tanpa auth)
echo "\n1. Testing GET /test/morning-reflection-attendance/attendance\n";
try {
    $response = Http::get($baseUrl . '/test/morning-reflection-attendance/attendance');
    echo "Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        $data = $response->json();
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . ($data['message'] ?? 'N/A') . "\n";
        echo "Total Records: " . ($data['total_records'] ?? count($data['data'] ?? [])) . "\n";
        
        if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
            $firstRecord = $data['data'][0];
            echo "\nSample Record:\n";
            echo "- ID: " . ($firstRecord['id'] ?? 'N/A') . "\n";
            echo "- Employee ID: " . ($firstRecord['employee_id'] ?? 'N/A') . "\n";
            echo "- Employee Name: " . ($firstRecord['employee_name'] ?? 'N/A') . "\n";
            echo "- Date: " . ($firstRecord['date'] ?? 'N/A') . "\n";
            echo "- Status: " . ($firstRecord['status'] ?? 'N/A') . "\n";
            
            if (isset($firstRecord['employee']) && $firstRecord['employee']) {
                echo "- Employee Data: " . ($firstRecord['employee']['nama_lengkap'] ?? 'N/A') . "\n";
            } else {
                echo "- Employee Data: null\n";
            }
        }
    } else {
        echo "Error Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 2: Get employees data untuk membandingkan
echo "\n2. Testing GET /employees (untuk membandingkan employee_id)\n";
try {
    $response = Http::get($baseUrl . '/employees');
    echo "Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        $employees = $response->json();
        echo "Total Employees: " . count($employees) . "\n";
        
        if (count($employees) > 0) {
            echo "Sample Employee IDs:\n";
            for ($i = 0; $i < min(5, count($employees)); $i++) {
                $emp = $employees[$i];
                echo "- ID: " . ($emp['id'] ?? 'N/A') . ", Name: " . ($emp['nama_lengkap'] ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "Error Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 3: Test dengan employee_id yang valid
echo "\n3. Testing POST dengan employee_id yang valid\n";
try {
    // Ambil employee_id pertama dari daftar employees
    $empResponse = Http::get($baseUrl . '/employees');
    if ($empResponse->successful()) {
        $employees = $empResponse->json();
        if (count($employees) > 0) {
            $validEmployeeId = $employees[0]['id'];
            
            $response = Http::post($baseUrl . '/test/morning-reflection-attendance/attend', [
                'employee_id' => $validEmployeeId,
                'date' => date('Y-m-d'),
                'status' => 'Hadir',
                'testing_mode' => true
            ]);
            
            echo "Status: " . $response->status() . "\n";
            echo "Response: " . $response->body() . "\n";
        } else {
            echo "No employees found in database\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING COMPLETED ===\n"; 