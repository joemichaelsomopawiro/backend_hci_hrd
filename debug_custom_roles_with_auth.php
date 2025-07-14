<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== DEBUG CUSTOM ROLES WITH PROPER AUTH ===\n";

// Cari user HR untuk mendapatkan token
$hrUser = User::where('role', 'HR')->first();

if (!$hrUser) {
    echo "No HR user found. Creating one...\n";
    $hrUser = User::create([
        'name' => 'Test HR User',
        'email' => 'hr@test.com',
        'password' => bcrypt('password'),
        'role' => 'HR',
        'phone' => '081234567890'
    ]);
}

echo "HR User found: {$hrUser->name} (ID: {$hrUser->id})\n";

// Buat token untuk user HR
$token = $hrUser->createToken('test-token')->plainTextToken;
echo "Generated token: {$token}\n\n";

// Test endpoint dengan token yang valid
function testEndpoint($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://127.0.0.1:8000{$url}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => fopen('php://temp', 'w+')
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "=== {$method} {$url} ===\n";
    if ($data) {
        echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: " . $response . "\n\n";
    
    curl_close($ch);
    
    return json_decode($response, true);
}

// Test GET all custom roles
testEndpoint('/api/custom-roles', 'GET', null, $token);

// Test POST create custom role
testEndpoint('/api/custom-roles', 'POST', [
    'role_name' => 'Test Role ' . time(),
    'description' => 'Test role created via debug script'
], $token);

// Test GET all roles
testEndpoint('/api/custom-roles/all-roles', 'GET', null, $token);

echo "=== DEBUG COMPLETED ===\n";