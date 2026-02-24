<?php

/*
 * Test script to verify Design Grafis API endpoint
 * This script makes an HTTP request to /api/pr/design-grafis/works
 */

$baseUrl = 'http://localhost';
$endpoint = '/api/pr/design-grafis/works';

echo "=== Testing Design Grafis API Endpoint ===\n\n";
echo "URL: {$baseUrl}{$endpoint}\n\n";

// Get a valid token from database
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('role', 'Design Grafis')->first();
if (!$user) {
    echo "ERROR: No user with 'Design Grafis' role found in database\n";
    echo "Creating a test token with any user...\n";
    $user = \App\Models\User::first();
}

if (!$user) {
    echo "ERROR: No users in database at all!\n";
    exit(1);
}

// Create a test token
$token = $user->createToken('test-token')->plainTextToken;
echo "Using token for user: {$user->name} (Role: {$user->role})\n\n";

// Make HTTP request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";

if ($httpCode === 200) {
    echo "✓ SUCCESS: API endpoint is working!\n\n";
    $data = json_decode($response, true);
    if (isset($data['data'])) {
        $count = is_array($data['data']) ? count($data['data']) : 0;
        echo "Number of works returned: $count\n";
        if ($count > 0) {
            echo "\nFirst work:\n";
            print_r($data['data'][0]);
        }
    } else {
        echo "Response:\n";
        print_r($data);
    }
} else {
    echo "✗ FAIL: Expected 200, got $httpCode\n\n";
    echo "Response:\n";
    echo $response . "\n";
}

echo "\n=== Test Complete ===\n";
