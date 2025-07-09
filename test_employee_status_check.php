<?php
/**
 * Test script untuk endpoint check employee status
 * 
 * Script ini akan menguji endpoint /api/auth/check-employee-status
 * dengan berbagai skenario
 */

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Employee;

echo "🧪 TEST EMPLOYEE STATUS CHECK ENDPOINT\n";
echo "=====================================\n\n";

// Test 1: User yang masih aktif di tabel employee
echo "Test 1: User yang masih aktif di tabel employee\n";
echo "------------------------------------------------\n";

$activeUser = User::whereHas('employee')->first();
if ($activeUser) {
    $token = $activeUser->createToken('test-token')->plainTextToken;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/check-employee-status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Clean up token
    $activeUser->tokens()->delete();
} else {
    echo "Tidak ada user aktif yang ditemukan\n\n";
}

// Test 2: User yang tidak memiliki employee_id (simulasi employee dihapus)
echo "Test 2: User yang tidak memiliki employee_id\n";
echo "---------------------------------------------\n";

$userWithoutEmployee = User::whereNull('employee_id')->first();
if ($userWithoutEmployee) {
    $token = $userWithoutEmployee->createToken('test-token')->plainTextToken;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/check-employee-status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Clean up token
    $userWithoutEmployee->tokens()->delete();
} else {
    echo "Tidak ada user tanpa employee_id yang ditemukan\n\n";
}

// Test 3: Request tanpa token (unauthorized)
echo "Test 3: Request tanpa token (unauthorized)\n";
echo "-----------------------------------------\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/check-employee-status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 4: User dengan employee_id yang tidak ada di tabel employees
echo "Test 4: User dengan employee_id yang tidak ada di tabel employees\n";
echo "----------------------------------------------------------------\n";

// Buat user dengan employee_id yang tidak ada
$fakeEmployeeId = 99999;
$userWithFakeEmployee = User::create([
    'name' => 'Test User Fake Employee',
    'email' => 'test.fake.employee@example.com',
    'phone' => '081234567890',
    'password' => bcrypt('password'),
    'employee_id' => $fakeEmployeeId,
    'role' => 'Employee'
]);

$token = $userWithFakeEmployee->createToken('test-token')->plainTextToken;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/check-employee-status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Clean up
$userWithFakeEmployee->tokens()->delete();
$userWithFakeEmployee->delete();

echo "✅ Testing selesai!\n"; 