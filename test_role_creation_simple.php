<?php

// Test sederhana untuk pembuatan role tanpa supervisor
echo "=== TEST ROLE CREATION WITHOUT SUPERVISOR ===\n\n";

// Test 1: Create employee role tanpa supervisor
echo "1. Testing POST /api/custom-roles (Employee tanpa supervisor)\n";
$url = 'http://localhost:8000/api/custom-roles';
$data = [
    'role_name' => 'Test Employee ' . time(),
    'description' => 'Test employee role without supervisor',
    'access_level' => 'employee',
    'department' => 'production',
    'supervisor_id' => null
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer your_token_here' // Ganti dengan token yang valid
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 2: Create manager role
echo "2. Testing POST /api/custom-roles (Manager role)\n";
$managerData = [
    'role_name' => 'Test Manager ' . time(),
    'description' => 'Test manager role',
    'access_level' => 'manager',
    'department' => 'production',
    'supervisor_id' => null
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($managerData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer your_token_here' // Ganti dengan token yang valid
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 3: Get form options
echo "3. Testing GET /api/custom-roles/form-options\n";
$url = 'http://localhost:8000/api/custom-roles/form-options';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer your_token_here' // Ganti dengan token yang valid
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

echo "=== TEST SELESAI ===\n";
echo "Jika HTTP Code 201 atau 200, berarti validasi supervisor berhasil dinonaktifkan!\n";
?> 