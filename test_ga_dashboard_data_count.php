<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Test endpoint GA Dashboard untuk melihat jumlah data
$baseUrl = 'http://localhost/backend_hci/public';

echo "=== TEST GA DASHBOARD DATA COUNT ===\n\n";

// Test 1: Data hari ini saja
echo "1. Testing data hari ini:\n";
$response = Http::get($baseUrl . '/api/ga-dashboard/worship-attendance');
echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    echo "Total records: " . $data['total_records'] . "\n";
    echo "Message: " . $data['message'] . "\n";
} else {
    echo "Error: " . $response->body() . "\n";
}

echo "\n";

// Test 2: Data 30 hari terakhir (all=true)
echo "2. Testing data 30 hari terakhir:\n";
$response = Http::get($baseUrl . '/api/ga-dashboard/worship-attendance?all=true');
echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    echo "Total records: " . $data['total_records'] . "\n";
    echo "Message: " . $data['message'] . "\n";
    
    // Tampilkan beberapa sample data
    if (isset($data['data']) && count($data['data']) > 0) {
        echo "\nSample data (5 pertama):\n";
        for ($i = 0; $i < min(5, count($data['data'])); $i++) {
            $record = $data['data'][$i];
            echo "- {$record['name']} ({$record['date']}): {$record['status']} [{$record['data_source']}]\n";
        }
    }
} else {
    echo "Error: " . $response->body() . "\n";
}

echo "\n";

// Test 3: Data tanggal tertentu
echo "3. Testing data tanggal tertentu (hari ini):\n";
$today = date('Y-m-d');
$response = Http::get($baseUrl . '/api/ga-dashboard/worship-attendance?date=' . $today);
echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    echo "Total records: " . $data['total_records'] . "\n";
    echo "Message: " . $data['message'] . "\n";
} else {
    echo "Error: " . $response->body() . "\n";
}

echo "\n=== END TEST ===\n"; 