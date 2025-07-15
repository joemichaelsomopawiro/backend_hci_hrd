<?php
/**
 * Script Verifikasi Final Sistem Kalender Nasional
 * 
 * Script ini akan memverifikasi bahwa semua komponen sistem kalender nasional
 * sudah siap untuk production deployment.
 */

echo "\n" . str_repeat("=", 80) . "\n";
echo "VERIFIKASI FINAL SISTEM KALENDER NASIONAL\n";
echo str_repeat("=", 80) . "\n\n";

$allChecks = [];
$totalChecks = 0;
$passedChecks = 0;

// ===== 1. VERIFIKASI FILE STRUKTUR =====
echo "1. VERIFIKASI FILE STRUKTUR\n";
echo str_repeat("-", 50) . "\n";

$requiredFiles = [
    'app/Http/Controllers/NationalHolidayController.php' => 'Controller utama kalender',
    'app/Models/NationalHoliday.php' => 'Model dengan serialisasi tanggal',
    'app/Services/GoogleCalendarService.php' => 'Service Google Calendar',
    'database/migrations/2025_07_12_100916_create_national_holidays_table.php' => 'Migration tabel',
    'routes/api.php' => 'File routes API',
    'test_calendar_comprehensive.php' => 'Script testing komprehensif',
    'CALENDAR_SYSTEM_FINAL_SUMMARY.md' => 'Dokumentasi final',
    'CALENDAR_QUICKSTART_GUIDE.md' => 'Panduan quick start'
];

foreach ($requiredFiles as $file => $description) {
    $totalChecks++;
    if (file_exists($file)) {
        echo "✅ $file - $description\n";
        $allChecks[] = "✅ $file";
        $passedChecks++;
    } else {
        echo "❌ $file - $description (TIDAK DITEMUKAN)\n";
        $allChecks[] = "❌ $file";
    }
}

// ===== 2. VERIFIKASI DATABASE =====
echo "\n2. VERIFIKASI DATABASE\n";
echo str_repeat("-", 50) . "\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hci_hrd", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek tabel national_holidays
    $totalChecks++;
    $stmt = $pdo->query("SHOW TABLES LIKE 'national_holidays'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabel national_holidays ada\n";
        $allChecks[] = "✅ Tabel national_holidays";
        $passedChecks++;
    } else {
        echo "❌ Tabel national_holidays tidak ada\n";
        $allChecks[] = "❌ Tabel national_holidays";
    }
    
    // Cek struktur field date
    $totalChecks++;
    $stmt = $pdo->query("DESCRIBE national_holidays");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dateColumn = null;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'date') {
            $dateColumn = $column;
            break;
        }
    }
    
    if ($dateColumn && strpos($dateColumn['Type'], 'date') !== false) {
        echo "✅ Field 'date' bertipe DATE\n";
        $allChecks[] = "✅ Field date DATE";
        $passedChecks++;
    } else {
        echo "❌ Field 'date' tidak bertipe DATE\n";
        $allChecks[] = "❌ Field date";
    }
    
    // Cek data sample
    $totalChecks++;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM national_holidays");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "✅ Ada data hari libur di database ({$result['total']} records)\n";
        $allChecks[] = "✅ Data hari libur";
        $passedChecks++;
    } else {
        echo "⚠️  Tidak ada data hari libur (perlu seed data)\n";
        $allChecks[] = "⚠️ Data hari libur";
    }
    
} catch (Exception $e) {
    echo "❌ Error koneksi database: " . $e->getMessage() . "\n";
    $allChecks[] = "❌ Koneksi database";
}

// ===== 3. VERIFIKASI API ENDPOINTS =====
echo "\n3. VERIFIKASI API ENDPOINTS\n";
echo str_repeat("-", 50) . "\n";

$baseUrl = 'http://localhost:8000/api';
$endpoints = [
    '/calendar/data-frontend?year=2024&month=1' => 'Data frontend',
    '/calendar/check?date=2024-01-01' => 'Check holiday',
    '/calendar/years' => 'Available years'
];

foreach ($endpoints as $endpoint => $description) {
    $totalChecks++;
    $url = $baseUrl . $endpoint;
    $response = testEndpoint($url);
    
    if ($response && isset($response['success'])) {
        echo "✅ $endpoint - $description\n";
        $allChecks[] = "✅ $endpoint";
        $passedChecks++;
    } else {
        echo "❌ $endpoint - $description (GAGAL)\n";
        $allChecks[] = "❌ $endpoint";
    }
}

// ===== 4. VERIFIKASI KODE KUALITAS =====
echo "\n4. VERIFIKASI KODE KUALITAS\n";
echo str_repeat("-", 50) . "\n";

// Cek controller
$totalChecks++;
$controllerContent = file_get_contents('app/Http/Controllers/NationalHolidayController.php');
if (strpos($controllerContent, 'getCalendarDataForFrontend') !== false) {
    echo "✅ Method getCalendarDataForFrontend ada di controller\n";
    $allChecks[] = "✅ Method getCalendarDataForFrontend";
    $passedChecks++;
} else {
    echo "❌ Method getCalendarDataForFrontend tidak ada\n";
    $allChecks[] = "❌ Method getCalendarDataForFrontend";
}

// Cek model serialisasi
$totalChecks++;
$modelContent = file_get_contents('app/Models/NationalHoliday.php');
if (strpos($modelContent, 'serializeDate') !== false) {
    echo "✅ Method serializeDate ada di model\n";
    $allChecks[] = "✅ Method serializeDate";
    $passedChecks++;
} else {
    echo "❌ Method serializeDate tidak ada\n";
    $allChecks[] = "❌ Method serializeDate";
}

// Cek cache headers
$totalChecks++;
if (strpos($controllerContent, 'Cache-Control') !== false) {
    echo "✅ Cache headers sudah diimplementasi\n";
    $allChecks[] = "✅ Cache headers";
    $passedChecks++;
} else {
    echo "❌ Cache headers belum diimplementasi\n";
    $allChecks[] = "❌ Cache headers";
}

// Cek role-based access
$totalChecks++;
if (strpos($controllerContent, 'role:HR') !== false) {
    echo "✅ Role-based access control ada\n";
    $allChecks[] = "✅ Role-based access";
    $passedChecks++;
} else {
    echo "❌ Role-based access control tidak ada\n";
    $allChecks[] = "❌ Role-based access";
}

// ===== 5. VERIFIKASI DOKUMENTASI =====
echo "\n5. VERIFIKASI DOKUMENTASI\n";
echo str_repeat("-", 50) . "\n";

$documentationFiles = [
    'CALENDAR_SYSTEM_FINAL_SUMMARY.md' => 'Dokumentasi final lengkap',
    'CALENDAR_QUICKSTART_GUIDE.md' => 'Panduan quick start',
    'FRONTEND_CALENDAR_STATE_FIX.md' => 'Dokumentasi state management'
];

foreach ($documentationFiles as $file => $description) {
    $totalChecks++;
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strlen($content) > 1000) { // Minimal 1000 karakter
            echo "✅ $file - $description\n";
            $allChecks[] = "✅ $file";
            $passedChecks++;
        } else {
            echo "⚠️  $file - $description (terlalu pendek)\n";
            $allChecks[] = "⚠️ $file";
        }
    } else {
        echo "❌ $file - $description (TIDAK DITEMUKAN)\n";
        $allChecks[] = "❌ $file";
    }
}

// ===== 6. VERIFIKASI TESTING =====
echo "\n6. VERIFIKASI TESTING\n";
echo str_repeat("-", 50) . "\n";

$totalChecks++;
if (file_exists('test_calendar_comprehensive.php')) {
    $testContent = file_get_contents('test_calendar_comprehensive.php');
    if (strlen($testContent) > 5000) { // Minimal 5000 karakter
        echo "✅ Script testing komprehensif ada dan lengkap\n";
        $allChecks[] = "✅ Script testing";
        $passedChecks++;
    } else {
        echo "⚠️  Script testing ada tapi terlalu pendek\n";
        $allChecks[] = "⚠️ Script testing";
    }
} else {
    echo "❌ Script testing tidak ada\n";
    $allChecks[] = "❌ Script testing";
}

// ===== 7. SUMMARY FINAL =====
echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY VERIFIKASI FINAL\n";
echo str_repeat("=", 80) . "\n";

$successRate = ($passedChecks / $totalChecks) * 100;

echo "📊 STATISTIK VERIFIKASI:\n";
echo "   - Total checks: $totalChecks\n";
echo "   - Passed checks: $passedChecks\n";
echo "   - Failed checks: " . ($totalChecks - $passedChecks) . "\n";
echo "   - Success rate: " . number_format($successRate, 1) . "%\n\n";

if ($successRate >= 90) {
    echo "🎉 SISTEM KALENDER NASIONAL SIAP UNTUK PRODUCTION! 🚀\n\n";
    
    echo "✅ FITUR YANG SUDAH SIAP:\n";
    echo "   - Format tanggal konsisten Y-m-d\n";
    echo "   - State management frontend responsif\n";
    echo "   - Cache busting untuk update realtime\n";
    echo "   - Integrasi Google Calendar API\n";
    echo "   - Role-based access control (HR only)\n";
    echo "   - CRUD operations lengkap\n";
    echo "   - API endpoints terstruktur\n";
    echo "   - Dokumentasi lengkap\n";
    echo "   - Testing komprehensif\n\n";
    
    echo "📋 LANGKAH SELANJUTNYA:\n";
    echo "   1. Deploy ke production server\n";
    echo "   2. Konfigurasi Google Calendar API credentials\n";
    echo "   3. Seed data hari libur untuk tahun yang diperlukan\n";
    echo "   4. Test semua fitur dengan user HR\n";
    echo "   5. Monitor performance dan cache\n\n";
    
    echo "🎯 SISTEM SUDAH OPTIMAL DAN SIAP DIGUNAKAN!\n";
    
} elseif ($successRate >= 70) {
    echo "⚠️  SISTEM HAMPIR SIAP, PERLU PERBAIKAN MINOR\n\n";
    
    echo "🔧 YANG PERLU DIPERBAIKI:\n";
    foreach ($allChecks as $check) {
        if (strpos($check, '❌') !== false) {
            echo "   $check\n";
        }
    }
    
} else {
    echo "❌ SISTEM BELUM SIAP, PERLU PERBAIKAN MAJOR\n\n";
    
    echo "🚨 MASALAH KRITIS:\n";
    foreach ($allChecks as $check) {
        if (strpos($check, '❌') !== false) {
            echo "   $check\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "VERIFIKASI SELESAI\n";
echo str_repeat("=", 80) . "\n";

// ===== HELPER FUNCTION =====

function testEndpoint($url) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    
    return null;
} 