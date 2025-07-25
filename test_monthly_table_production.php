<?php
// Test monthly table di production server
echo "=== TEST MONTHLY TABLE PRODUCTION ===\n\n";

// Test dengan berbagai bulan dan tahun
$testCases = [
    ['month' => 7, 'year' => 2025],
    ['month' => 6, 'year' => 2025],
    ['month' => 5, 'year' => 2025],
    ['month' => 7, 'year' => 2024],
    ['month' => 6, 'year' => 2024],
    ['month' => 5, 'year' => 2024],
    ['month' => 12, 'year' => 2024],
    ['month' => 1, 'year' => 2025],
];

$baseUrl = 'https://api.hopemedia.id/api';
$endpoint = '/attendance/monthly-table';

foreach ($testCases as $testCase) {
    $month = $testCase['month'];
    $year = $testCase['year'];
    
    echo "Testing: Month $month, Year $year\n";
    
    $params = [
        'month' => $month,
        'year' => $year
    ];
    
    $url = $baseUrl . $endpoint . '?' . http_build_query($params);
    
    // Setup cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Test-Script/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ cURL Error: $error\n";
        continue;
    }
    
    if ($httpCode !== 200) {
        echo "  ❌ HTTP Error: $httpCode\n";
        continue;
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        echo "  ❌ Failed to parse JSON\n";
        continue;
    }
    
    $records = count($data['data']['records'] ?? []);
    echo "  ✅ Records: $records\n";
    
    if ($records > 0) {
        $firstRecord = $data['data']['records'][0];
        echo "  📊 First record: {$firstRecord['nama']} (PIN: '{$firstRecord['user_pin']}')\n";
        echo "  📊 Total Hadir: {$firstRecord['total_hadir']}, Total Jam Kerja: {$firstRecord['total_jam_kerja']}, Total Absen: {$firstRecord['total_absen']}\n";
    }
    
    echo "\n";
}

echo "✅ All tests completed!\n"; 