<?php
// Test route langsung di server
// Jalankan via SSH: php test_route_direct.php

echo "=== TEST ROUTE DIRECT ===\n\n";

// Test dengan curl internal
$url = 'http://localhost/api/attendance/monthly-table?month=7&year=2025';

echo "🔗 Testing internal URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "📊 HTTP Status Code: $httpCode\n";

if ($error) {
    echo "❌ cURL Error: $error\n";
} else {
    echo "✅ Response received\n";
    echo "Response: $response\n";
}

echo "\n=== SELESAI ===\n"; 