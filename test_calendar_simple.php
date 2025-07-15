<?php
/**
 * Script Testing Sederhana Sistem Kalender Nasional
 * 
 * Script ini akan menguji API endpoints kalender nasional
 * tanpa memerlukan autentikasi untuk endpoint public.
 */

echo "\n" . str_repeat("=", 60) . "\n";
echo "TESTING SEDERHANA SISTEM KALENDER NASIONAL\n";
echo str_repeat("=", 60) . "\n\n";

$baseUrl = 'http://localhost:8000/api';
$results = [];

// ===== 1. TEST ENDPOINT DATA FRONTEND =====
echo "1. Testing endpoint /api/calendar/data-frontend...\n";
$url = $baseUrl . '/calendar/data-frontend?year=2024&month=1';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint data-frontend BERHASIL\n";
    if (isset($response['data']) && is_array($response['data'])) {
        echo "   - Jumlah hari libur: " . count($response['data']) . "\n";
        foreach ($response['data'] as $date => $holiday) {
            echo "     📅 $date: {$holiday['name']}\n";
        }
    }
    $results['data-frontend'] = 'SUCCESS';
} else {
    echo "❌ Endpoint data-frontend GAGAL\n";
    if ($response) {
        echo "   - Response: " . json_encode($response) . "\n";
    }
    $results['data-frontend'] = 'FAILED';
}

// ===== 2. TEST ENDPOINT CHECK HOLIDAY =====
echo "\n2. Testing endpoint /api/calendar/check...\n";
$url = $baseUrl . '/calendar/check?date=2024-01-01';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint check holiday BERHASIL\n";
    if (isset($response['data'])) {
        echo "   - Date: " . $response['data']['date'] . "\n";
        echo "   - Is Holiday: " . ($response['data']['is_holiday'] ? 'Yes' : 'No') . "\n";
        echo "   - Holiday Name: " . ($response['data']['holiday_name'] ?? 'None') . "\n";
    }
    $results['check-holiday'] = 'SUCCESS';
} else {
    echo "❌ Endpoint check holiday GAGAL\n";
    if ($response) {
        echo "   - Response: " . json_encode($response) . "\n";
    }
    $results['check-holiday'] = 'FAILED';
}

// ===== 3. TEST ENDPOINT YEARS =====
echo "\n3. Testing endpoint /api/calendar/years...\n";
$url = $baseUrl . '/calendar/years';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint years BERHASIL\n";
    if (isset($response['data']) && is_array($response['data'])) {
        echo "   - Available years: " . implode(', ', $response['data']) . "\n";
    }
    $results['years'] = 'SUCCESS';
} else {
    echo "❌ Endpoint years GAGAL\n";
    if ($response) {
        echo "   - Response: " . json_encode($response) . "\n";
    }
    $results['years'] = 'FAILED';
}

// ===== 4. TEST DATABASE CONNECTION =====
echo "\n4. Testing database connection...\n";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=hci_hrd", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek tabel national_holidays
    $stmt = $pdo->query("SHOW TABLES LIKE 'national_holidays'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabel national_holidays ada\n";
        
        // Cek data
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM national_holidays");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   - Total records: {$result['total']}\n";
        
        // Cek sample data
        $stmt = $pdo->query("SELECT date, name, type FROM national_holidays ORDER BY date LIMIT 3");
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($holidays) > 0) {
            echo "   - Sample data:\n";
            foreach ($holidays as $holiday) {
                echo "     📅 {$holiday['date']}: {$holiday['name']} ({$holiday['type']})\n";
            }
        }
        
        $results['database'] = 'SUCCESS';
    } else {
        echo "❌ Tabel national_holidays tidak ada\n";
        $results['database'] = 'FAILED';
    }
} catch (Exception $e) {
    echo "❌ Error database: " . $e->getMessage() . "\n";
    $results['database'] = 'FAILED';
}

// ===== 5. SUMMARY =====
echo "\n" . str_repeat("=", 60) . "\n";
echo "SUMMARY TESTING\n";
echo str_repeat("=", 60) . "\n";

$totalTests = count($results);
$successTests = count(array_filter($results, function($result) {
    return $result === 'SUCCESS';
}));

echo "📊 Hasil Testing:\n";
foreach ($results as $test => $result) {
    $status = $result === 'SUCCESS' ? '✅' : '❌';
    echo "   $status $test: $result\n";
}

echo "\n📈 Statistik:\n";
echo "   - Total tests: $totalTests\n";
echo "   - Success: $successTests\n";
echo "   - Failed: " . ($totalTests - $successTests) . "\n";
echo "   - Success rate: " . number_format(($successTests / $totalTests) * 100, 1) . "%\n";

if ($successTests === $totalTests) {
    echo "\n🎉 SEMUA TEST BERHASIL! Sistem kalender nasional siap digunakan.\n";
} elseif ($successTests >= ($totalTests * 0.7)) {
    echo "\n⚠️  Sebagian besar test berhasil. Perlu perbaikan minor.\n";
} else {
    echo "\n❌ Banyak test gagal. Perlu perbaikan major.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// ===== HELPER FUNCTION =====

function testApiEndpoint($url) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Calendar-Test-Script/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "   - cURL Error: $error\n";
        return null;
    }
    
    if ($response === false) {
        echo "   - Response failed\n";
        return null;
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        } else {
            echo "   - JSON decode error: " . json_last_error_msg() . "\n";
            return null;
        }
    } else {
        echo "   - HTTP Error: $httpCode\n";
        return null;
    }
}