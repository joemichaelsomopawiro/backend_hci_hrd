<?php
// Test script untuk cek endpoint API monthly table
// Jalankan via SSH: php test_monthly_table_api.php

echo "=== TEST MONTHLY TABLE API ===\n\n";

// Test endpoint API
$baseUrl = 'https://api.hopemedia.id/api';
$endpoint = '/attendance/monthly-table';
$params = [
    'month' => 7,
    'year' => 2025
];

$url = $baseUrl . $endpoint . '?' . http_build_query($params);

echo "🔗 Testing URL: $url\n\n";

// Setup cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Test-Script/1.0');

// Headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

echo "📡 Sending request...\n";

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "📊 HTTP Status Code: $httpCode\n";

if ($error) {
    echo "❌ cURL Error: $error\n";
} else {
    echo "✅ Response received\n\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if ($data) {
            echo "📋 Response Data:\n";
            echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            
            if (isset($data['message'])) {
                echo "Message: " . $data['message'] . "\n";
            }
            
            if (isset($data['data'])) {
                echo "\n📊 Data Details:\n";
                echo "Month: " . $data['data']['month'] . "\n";
                echo "Year: " . $data['data']['year'] . "\n";
                echo "Working Days: " . count($data['data']['working_days']) . "\n";
                echo "Records: " . count($data['data']['records']) . "\n";
                
                if (count($data['data']['records']) > 0) {
                    echo "\n📋 Sample Records:\n";
                    foreach (array_slice($data['data']['records'], 0, 3) as $record) {
                        echo "- User: {$record['nama']}, PIN: {$record['user_pin']}, Total Hadir: {$record['total_hadir']}\n";
                    }
                }
            }
        } else {
            echo "❌ Invalid JSON response\n";
            echo "Raw response: $response\n";
        }
    } else {
        echo "❌ HTTP Error: $httpCode\n";
        echo "Response: $response\n";
    }
}

echo "\n=== SELESAI ===\n"; 