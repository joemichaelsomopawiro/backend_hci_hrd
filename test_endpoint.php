<?php
// Test endpoint untuk debug CORS dan unified endpoints

$url = 'http://127.0.0.1:8000/api/unified/songs';
$token = 'your_token_here'; // Ganti dengan token yang valid

echo "=== Testing Unified Songs Endpoint ===\n\n";

// Test 1: Test tanpa token
echo "1. Testing without token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: {$httpCode}\n";
if ($httpCode === 401) {
    echo "   ✅ Expected 401 (Unauthorized) - endpoint exists\n";
} else {
    echo "   ❌ Unexpected response: {$httpCode}\n";
}
echo "\n";

// Test 2: Test dengan token (jika ada)
if ($token !== 'your_token_here') {
    echo "2. Testing with token...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   HTTP Code: {$httpCode}\n";
    if ($httpCode === 200) {
        echo "   ✅ Success with token\n";
    } else {
        echo "   ❌ Error with token: {$httpCode}\n";
    }
    echo "\n";
}

echo "=== Testing Unified Singers Endpoint ===\n\n";

$url = 'http://127.0.0.1:8000/api/unified/singers';

// Test 1: Test tanpa token
echo "1. Testing without token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: {$httpCode}\n";
if ($httpCode === 401) {
    echo "   ✅ Expected 401 (Unauthorized) - endpoint exists\n";
} else {
    echo "   ❌ Unexpected response: {$httpCode}\n";
}
echo "\n";

echo "=== Test Complete ===\n";

