<?php

// Test Login Endpoint secara langsung
echo "=== TEST LOGIN ENDPOINT ===\n";

$apiUrl = 'https://api.hopechannel.id/api/auth/login';

// Test data
$testData = [
    'login' => 'test@example.com',
    'password' => 'password123'
];

echo "\n1. Testing login endpoint with curl...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);

curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . $response . "\n";

// Show verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "\nVerbose output:\n" . $verboseLog . "\n";

// Test 2: Simple GET request to check if server is reachable
echo "\n2. Testing server reachability...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.hopechannel.id');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Server HTTP Code: " . $httpCode . "\n";
echo "Server Error: " . ($error ?: 'None') . "\n";

// Test 3: Check if API base URL is accessible
echo "\n3. Testing API base URL...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.hopechannel.id/api');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "API Base HTTP Code: " . $httpCode . "\n";
echo "API Base Error: " . ($error ?: 'None') . "\n";

// Test 4: Check DNS resolution
echo "\n4. DNS Resolution Test...\n";
$host = 'api.hopechannel.id';
$ip = gethostbyname($host);
echo "Host: " . $host . "\n";
echo "IP: " . ($ip !== $host ? $ip : 'DNS resolution failed') . "\n";

// Test 5: Port connectivity test
echo "\n5. Port Connectivity Test...\n";
$connection = @fsockopen($host, 443, $errno, $errstr, 10);
if ($connection) {
    echo "✓ Port 443 (HTTPS) is reachable\n";
    fclose($connection);
} else {
    echo "✗ Port 443 (HTTPS) is not reachable: $errstr ($errno)\n";
}

// Test 6: Check SSL certificate
echo "\n6. SSL Certificate Test...\n";
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'capture_peer_cert' => true,
    ]
]);

$result = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
if ($result) {
    $cert = stream_context_get_params($result);
    if (isset($cert['options']['ssl']['peer_certificate'])) {
        echo "✓ SSL certificate is valid\n";
        $certInfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
        echo "Certificate subject: " . $certInfo['subject']['CN'] . "\n";
        echo "Certificate valid until: " . date('Y-m-d H:i:s', $certInfo['validTo_time_t']) . "\n";
    }
    fclose($result);
} else {
    echo "✗ SSL connection failed: $errstr ($errno)\n";
}

// Test 7: Test with different User-Agent
echo "\n7. Testing with different User-Agent...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: PostmanRuntime/7.32.3'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code (Postman UA): " . $httpCode . "\n";
echo "Error (Postman UA): " . ($error ?: 'None') . "\n";
echo "Response (Postman UA): " . $response . "\n";

// Test 8: Test without SSL verification
echo "\n8. Testing without SSL verification...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code (no SSL verify): " . $httpCode . "\n";
echo "Error (no SSL verify): " . ($error ?: 'None') . "\n";
echo "Response (no SSL verify): " . $response . "\n";
echo "Total time: " . $info['total_time'] . " seconds\n";

echo "\n=== TEST COMPLETE ===\n"; 