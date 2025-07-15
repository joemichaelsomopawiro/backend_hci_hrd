<?php

/**
 * Test Export Excel - Simple Version
 */

echo "=== TEST EXPORT EXCEL GA DASHBOARD ===\n\n";

// Base URL
$baseUrl = 'http://127.0.0.1:8000';

// Test 1: Test endpoint tanpa auth (harusnya return 401)
echo "1. Test endpoint tanpa authentication...\n";
$url = $baseUrl . '/api/ga-dashboard/export-worship-attendance';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 401) {
    echo "✅ Berhasil! Endpoint memerlukan authentication\n";
} else {
    echo "❌ Unexpected response\n";
}
echo "\n";

// Test 2: Test endpoint dengan auth dummy (harusnya return 401 juga)
echo "2. Test endpoint dengan auth dummy...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer dummy_token',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 401) {
    echo "✅ Berhasil! Token tidak valid\n";
} else {
    echo "❌ Unexpected response\n";
}
echo "\n";

// Test 3: Test route exists
echo "3. Test apakah route terdaftar...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/ga-dashboard/worship-attendance');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 401) {
    echo "✅ Route worship-attendance ada dan memerlukan auth\n";
} elseif ($httpCode === 404) {
    echo "❌ Route worship-attendance tidak ditemukan\n";
} else {
    echo "⚠️ Unexpected response: $httpCode\n";
}
echo "\n";

// Test 4: Test route leave-requests
echo "4. Test route leave-requests...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/ga-dashboard/leave-requests');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 401) {
    echo "✅ Route leave-requests ada dan memerlukan auth\n";
} elseif ($httpCode === 404) {
    echo "❌ Route leave-requests tidak ditemukan\n";
} else {
    echo "⚠️ Unexpected response: $httpCode\n";
}
echo "\n";

// Test 5: Test dengan method POST (harusnya 405 Method Not Allowed)
echo "5. Test dengan method POST...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 405) {
    echo "✅ Berhasil! Method POST tidak diizinkan (hanya GET)\n";
} else {
    echo "⚠️ Unexpected response: $httpCode\n";
}
echo "\n";

echo "=== TEST SELESAI ===\n";
echo "Jika semua test berhasil, berarti endpoint sudah terdaftar dengan benar.\n";
echo "Untuk test lengkap dengan authentication, gunakan file test_export_worship_attendance.php\n"; 