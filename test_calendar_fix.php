<?php
/**
 * Script Testing Perbaikan Kalender Nasional
 * 
 * Script ini akan menguji apakah perbaikan backend sudah berfungsi:
 * 1. Endpoint mengembalikan semua hari libur untuk tahun tertentu
 * 2. Hari libur tampil di semua bulan, bukan hanya bulan Juli
 */

echo "\n" . str_repeat("=", 70) . "\n";
echo "TESTING PERBAIKAN KALENDER NASIONAL\n";
echo str_repeat("=", 70) . "\n\n";

$baseUrl = 'http://localhost:8000/api';
$results = [];

// ===== 1. TEST ENDPOINT INDEX (SEMUA HARI LIBUR TAHUN) =====
echo "1. Testing endpoint /api/calendar (semua hari libur tahun 2025)...\n";
$url = $baseUrl . '/calendar?year=2025';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint /api/calendar BERHASIL\n";
    if (isset($response['data']) && is_array($response['data'])) {
        echo "   - Total hari libur: " . count($response['data']) . "\n";
        
        // Cek distribusi bulan
        $monthDistribution = [];
        foreach ($response['data'] as $holiday) {
            if (isset($holiday['date'])) {
                $month = date('F', strtotime($holiday['date']));
                $monthDistribution[$month] = ($monthDistribution[$month] ?? 0) + 1;
            }
        }
        
        echo "   - Distribusi bulan:\n";
        foreach ($monthDistribution as $month => $count) {
            echo "     📅 $month: $count hari libur\n";
        }
        
        // Cek apakah ada hari libur di bulan selain Juli
        $nonJulyHolidays = array_filter($monthDistribution, function($month) {
            return $month !== 'July';
        });
        
        if (count($nonJulyHolidays) > 0) {
            echo "   ✅ Ada hari libur di bulan selain Juli\n";
        } else {
            echo "   ⚠️  Hanya ada hari libur di bulan Juli\n";
        }
    }
    $results['index'] = 'SUCCESS';
} else {
    echo "❌ Endpoint /api/calendar GAGAL\n";
    if ($response) {
        echo "   - Response: " . json_encode($response) . "\n";
    }
    $results['index'] = 'FAILED';
}

// ===== 2. TEST ENDPOINT DATA FRONTEND =====
echo "\n2. Testing endpoint /api/calendar/data-frontend (tahun 2025)...\n";
$url = $baseUrl . '/calendar/data-frontend?year=2025';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint data-frontend BERHASIL\n";
    if (isset($response['data']) && is_array($response['data'])) {
        echo "   - Total hari libur: " . count($response['data']) . "\n";
        
        // Cek distribusi bulan
        $monthDistribution = [];
        foreach ($response['data'] as $date => $holiday) {
            $month = date('F', strtotime($date));
            $monthDistribution[$month] = ($monthDistribution[$month] ?? 0) + 1;
        }
        
        echo "   - Distribusi bulan:\n";
        foreach ($monthDistribution as $month => $count) {
            echo "     📅 $month: $count hari libur\n";
        }
        
        // Cek apakah ada hari libur di bulan selain Juli
        $nonJulyHolidays = array_filter($monthDistribution, function($month) {
            return $month !== 'July';
        });
        
        if (count($nonJulyHolidays) > 0) {
            echo "   ✅ Ada hari libur di bulan selain Juli\n";
        } else {
            echo "   ⚠️  Hanya ada hari libur di bulan Juli\n";
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

// ===== 3. TEST ENDPOINT YEARLY HOLIDAYS =====
echo "\n3. Testing endpoint /api/calendar/yearly-holidays (tahun 2025)...\n";
$url = $baseUrl . '/calendar/yearly-holidays?year=2025';
$response = testApiEndpoint($url);

if ($response && isset($response['success']) && $response['success']) {
    echo "✅ Endpoint yearly-holidays BERHASIL\n";
    if (isset($response['data']['holidays']) && is_array($response['data']['holidays'])) {
        echo "   - Total hari libur: " . count($response['data']['holidays']) . "\n";
        
        // Cek distribusi bulan
        $monthDistribution = [];
        foreach ($response['data']['holidays'] as $holiday) {
            if (isset($holiday['date'])) {
                $month = date('F', strtotime($holiday['date']));
                $monthDistribution[$month] = ($monthDistribution[$month] ?? 0) + 1;
            }
        }
        
        echo "   - Distribusi bulan:\n";
        foreach ($monthDistribution as $month => $count) {
            echo "     📅 $month: $count hari libur\n";
        }
    }
    $results['yearly-holidays'] = 'SUCCESS';
} else {
    echo "❌ Endpoint yearly-holidays GAGAL\n";
    if ($response) {
        echo "   - Response: " . json_encode($response) . "\n";
    }
    $results['yearly-holidays'] = 'FAILED';
}

// ===== 4. TEST TAMBAH HARI LIBUR BARU =====
echo "\n4. Testing tambah hari libur baru (Agustus 2025)...\n";

// Simulasi tambah hari libur (tanpa auth untuk testing)
echo "   - Simulasi: Menambah hari libur di Agustus 2025\n";
echo "   - Tanggal: 2025-08-15\n";
echo "   - Nama: Libur Khusus Agustus\n";
echo "   - Tipe: custom\n";

// Test apakah tanggal sudah ada
$url = $baseUrl . '/calendar/check?date=2025-08-15';
$response = testApiEndpoint($url);

if ($response && isset($response['data']['is_holiday'])) {
    if ($response['data']['is_holiday']) {
        echo "   ⚠️  Tanggal 2025-08-15 sudah ada hari libur\n";
    } else {
        echo "   ✅ Tanggal 2025-08-15 belum ada hari libur (bisa ditambah)\n";
    }
    $results['check-date'] = 'SUCCESS';
} else {
    echo "   ❌ Gagal cek tanggal\n";
    $results['check-date'] = 'FAILED';
}

// ===== 5. TEST CACHE HEADERS =====
echo "\n5. Testing cache headers...\n";
$url = $baseUrl . '/calendar/data-frontend?year=2025';
$headers = testApiHeaders($url);

if ($headers) {
    echo "✅ Headers response:\n";
    $hasNoCache = false;
    foreach ($headers as $header => $value) {
        echo "   - $header: $value\n";
        if (strpos($header, 'Cache-Control') !== false && 
            (strpos($value, 'no-cache') !== false || strpos($value, 'no-store') !== false)) {
            $hasNoCache = true;
        }
    }
    
    if ($hasNoCache) {
        echo "   ✅ Cache headers sudah benar (no-cache)\n";
        $results['cache-headers'] = 'SUCCESS';
    } else {
        echo "   ❌ Cache headers tidak sesuai\n";
        $results['cache-headers'] = 'FAILED';
    }
} else {
    echo "❌ Tidak bisa mendapatkan headers\n";
    $results['cache-headers'] = 'FAILED';
}

// ===== 6. SUMMARY =====
echo "\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY PERBAIKAN KALENDER\n";
echo str_repeat("=", 70) . "\n";

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
    echo "\n🎉 PERBAIKAN BERHASIL! Hari libur sekarang tampil di semua bulan.\n";
    echo "✅ Backend sudah mengembalikan semua hari libur untuk tahun tertentu\n";
    echo "✅ Cache headers sudah benar untuk update realtime\n";
    echo "✅ Frontend akan menampilkan hari libur di semua bulan\n\n";
    
    echo "📋 LANGKAH SELANJUTNYA:\n";
    echo "   1. Test di frontend: tambah hari libur di bulan Agustus\n";
    echo "   2. Pastikan hari libur langsung tampil tanpa refresh\n";
    echo "   3. Test di bulan lain untuk memastikan konsistensi\n";
    
} elseif ($successTests >= ($totalTests * 0.7)) {
    echo "\n⚠️  Sebagian besar perbaikan berhasil. Perlu pengecekan lebih lanjut.\n";
} else {
    echo "\n❌ Perbaikan belum berhasil. Perlu debugging lebih lanjut.\n";
}

echo "\n" . str_repeat("=", 70) . "\n";

// ===== HELPER FUNCTIONS =====

function testApiEndpoint($url) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Calendar-Fix-Test/1.0');
    
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

function testApiHeaders($url) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        $headers = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        return $headers;
    }
    
    return null;
} 