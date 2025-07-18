<?php

// Test Zoom Link API
echo "=== TEST ZOOM LINK API ===\n\n";

// Test 1: Get public zoom link
echo "1. Testing GET /api/zoom-link (public)\n";
$url = 'http://localhost:8000/api/zoom-link';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 2: Get zoom link with auth (GA)
echo "2. Testing GET /api/ga/zoom-link (with auth)\n";
$url = 'http://localhost:8000/api/ga/zoom-link';

// Get token first (you need to replace with actual token)
$token = 'your_token_here'; // Replace with actual token

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 3: Update zoom link (GA only)
echo "3. Testing POST /api/ga/zoom-link (update)\n";
$url = 'http://localhost:8000/api/ga/zoom-link';
$data = [
    'zoom_link' => 'https://us06web.zoom.us/j/123456789?pwd=abc123',
    'meeting_id' => '123456789',
    'passcode' => 'abc123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

echo "=== TEST SELESAI ===\n";
?> 