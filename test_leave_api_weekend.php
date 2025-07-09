<?php

// Test API untuk memverifikasi perhitungan cuti yang mengecualikan weekend
// Pastikan server Laravel sudah berjalan di localhost:8000

$baseUrl = 'http://localhost:8000/api';

// Fungsi untuk membuat request API
function makeApiRequest($method, $endpoint, $data = null, $token = null) {
    $url = $GLOBALS['baseUrl'] . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

echo "=== TEST API PERHITUNGAN CUTI (WEEKEND EXCLUDED) ===\n\n";

// Test cases untuk API
$testCases = [
    [
        'name' => 'Cuti Jumat ke Senin (melalui weekend)',
        'start_date' => '2025-01-24', // Jumat
        'end_date' => '2025-01-27',   // Senin
        'expected_days' => 2,         // Jumat + Senin (Sabtu-Minggu tidak dihitung)
        'leave_type' => 'annual'
    ],
    [
        'name' => 'Cuti Senin ke Jumat (seminggu penuh)',
        'start_date' => '2025-01-20', // Senin
        'end_date' => '2025-01-24',   // Jumat
        'expected_days' => 5,         // Senin-Jumat
        'leave_type' => 'annual'
    ],
    [
        'name' => 'Cuti Sabtu ke Minggu (weekend)',
        'start_date' => '2025-01-25', // Sabtu
        'end_date' => '2025-01-26',   // Minggu
        'expected_days' => 0,         // Tidak ada hari kerja
        'leave_type' => 'annual'
    ]
];

// Catatan: Untuk menjalankan test ini, Anda perlu:
// 1. Login dan dapatkan token
// 2. Pastikan ada data employee dan leave quota
// 3. Server Laravel berjalan di localhost:8000

echo "INSTRUKSI TEST:\n";
echo "1. Pastikan server Laravel berjalan: php artisan serve\n";
echo "2. Login sebagai employee dan dapatkan token\n";
echo "3. Update variabel \$token di bawah ini\n";
echo "4. Jalankan: php test_leave_api_weekend.php\n\n";

// TODO: Update token setelah login
$token = 'YOUR_TOKEN_HERE';

if ($token === 'YOUR_TOKEN_HERE') {
    echo "⚠️  PERINGATAN: Token belum diupdate!\n";
    echo "Silakan login dan update token di file ini.\n\n";
    
    echo "Contoh cara mendapatkan token:\n";
    echo "curl -X POST http://localhost:8000/api/login \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -d '{\"email\":\"employee@example.com\",\"password\":\"password\"}'\n\n";
    
    echo "Test cases yang akan dijalankan:\n";
    foreach ($testCases as $test) {
        echo "- {$test['name']}\n";
        echo "  Start: {$test['start_date']}\n";
        echo "  End: {$test['end_date']}\n";
        echo "  Expected: {$test['expected_days']} hari\n\n";
    }
    
    exit;
}

// Test API calls
foreach ($testCases as $test) {
    echo "Testing: {$test['name']}\n";
    echo "Start Date: {$test['start_date']}\n";
    echo "End Date: {$test['end_date']}\n";
    echo "Expected Days: {$test['expected_days']}\n";
    
    $requestData = [
        'leave_type' => $test['leave_type'],
        'start_date' => $test['start_date'],
        'end_date' => $test['end_date'],
        'reason' => 'Test perhitungan cuti - ' . $test['name']
    ];
    
    $result = makeApiRequest('POST', '/leave-requests', $requestData, $token);
    
    echo "Status Code: {$result['status_code']}\n";
    
    if ($result['status_code'] === 201) {
        $actualDays = $result['response']['data']['total_days'] ?? 'N/A';
        echo "Actual Days: {$actualDays}\n";
        
        if ($actualDays == $test['expected_days']) {
            echo "✅ PASS - Perhitungan benar\n";
        } else {
            echo "❌ FAIL - Perhitungan salah\n";
        }
    } else {
        echo "❌ FAIL - Request gagal\n";
        echo "Response: " . json_encode($result['response']) . "\n";
    }
    
    echo "---\n";
}

echo "\n=== KESIMPULAN ===\n";
echo "Jika semua test PASS, berarti perhitungan cuti sudah benar\n";
echo "dan tidak menghitung hari Sabtu dan Minggu.\n"; 