<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== TESTING MORNING REFLECTION ATTENDANCE ===\n";

$baseUrl = 'http://localhost:8000/api';

// Test 1: Get attendance data
echo "\n1. Testing GET /morning-reflection-attendance/attendance\n";
try {
    $response = Http::get($baseUrl . '/morning-reflection-attendance/attendance');
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 2: Post attendance (tanpa auth untuk testing)
echo "\n2. Testing POST /test/morning-reflection-attendance/attend\n";
try {
    $response = Http::post($baseUrl . '/test/morning-reflection-attendance/attend', [
        'employee_id' => 1,
        'date' => date('Y-m-d'),
        'status' => 'Hadir',
        'join_time' => date('Y-m-d H:i:s'),
        'testing_mode' => true
    ]);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 3: Test dengan employee_id yang tidak ada
echo "\n3. Testing POST dengan employee_id yang tidak ada\n";
try {
    $response = Http::post($baseUrl . '/test/morning-reflection-attendance/attend', [
        'employee_id' => 999999,
        'date' => date('Y-m-d'),
        'status' => 'Hadir',
        'testing_mode' => true
    ]);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING COMPLETED ===\n"; 