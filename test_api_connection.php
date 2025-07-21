<?php

// Test API Connection untuk debugging masalah login
echo "=== TEST API CONNECTION ===\n";

$apiUrl = 'https://api.hopechannel.id/api/auth/login';

// Test 1: Basic connection test
echo "\n1. Testing basic connection...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . substr($response, 0, 500) . "\n";

// Test 2: POST request test
echo "\n2. Testing POST request...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'login' => 'test@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . $response . "\n";

// Test 3: Check if server is reachable
echo "\n3. Testing server reachability...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.hopechannel.id');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Server HTTP Code: " . $httpCode . "\n";
echo "Server Error: " . ($error ?: 'None') . "\n";

// Test 4: DNS resolution
echo "\n4. Testing DNS resolution...\n";
$ip = gethostbyname('api.hopechannel.id');
echo "IP Address: " . ($ip !== 'api.hopechannel.id' ? $ip : 'DNS resolution failed') . "\n";

// Test 5: Port connectivity
echo "\n5. Testing port connectivity...\n";
$connection = @fsockopen('api.hopechannel.id', 443, $errno, $errstr, 10);
if ($connection) {
    echo "Port 443 (HTTPS) is reachable\n";
    fclose($connection);
} else {
    echo "Port 443 (HTTPS) is not reachable: $errstr ($errno)\n";
}

echo "\n=== TEST COMPLETE ===\n"; 