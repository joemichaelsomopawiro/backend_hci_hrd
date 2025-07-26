<?php
// Test API monthly table langsung
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
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ HTTP Error: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

echo "✅ Request successful!\n\n";

// Parse response
$data = json_decode($response, true);

if (!$data) {
    echo "❌ Failed to parse JSON response\n";
    echo "Raw response: $response\n";
    exit(1);
}

echo "📋 Response Analysis:\n";
echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "Message: " . ($data['message'] ?? 'No message') . "\n";

if (isset($data['data'])) {
    $responseData = $data['data'];
    echo "Month: " . ($responseData['month'] ?? 'N/A') . "\n";
    echo "Year: " . ($responseData['year'] ?? 'N/A') . "\n";
    echo "Working Days: " . count($responseData['working_days'] ?? []) . "\n";
    echo "Records: " . count($responseData['records'] ?? []) . "\n";
    
    if (isset($responseData['records']) && count($responseData['records']) > 0) {
        echo "\n📊 Sample Records:\n";
        foreach (array_slice($responseData['records'], 0, 3) as $index => $record) {
            echo ($index + 1) . ". {$record['nama']} (PIN: {$record['user_pin']})\n";
            echo "   Total Hadir: {$record['total_hadir']}\n";
            echo "   Total Jam Kerja: {$record['total_jam_kerja']}\n";
            echo "   Total Absen: {$record['total_absen']}\n";
            echo "   Daily Data Keys: " . implode(', ', array_keys($record['daily_data'] ?? [])) . "\n";
            echo "\n";
        }
    } else {
        echo "\n❌ No records found!\n";
    }
    
    if (isset($responseData['working_days']) && count($responseData['working_days']) > 0) {
        echo "📅 Working Days:\n";
        foreach (array_slice($responseData['working_days'], 0, 5) as $day) {
            echo "- Day {$day['day']} ({$day['dayName']}): {$day['date']}\n";
        }
        if (count($responseData['working_days']) > 5) {
            echo "... and " . (count($responseData['working_days']) - 5) . " more days\n";
        }
    }
}

echo "\n✅ Test completed!\n"; 