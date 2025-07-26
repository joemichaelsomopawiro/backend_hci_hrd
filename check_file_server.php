<?php
// Script untuk cek file di server
// Jalankan via SSH: php check_file_server.php

echo "=== CHECK FILE ON SERVER ===\n\n";

// Cek apakah file routes/api.php ada
$routesFile = 'routes/api.php';
if (file_exists($routesFile)) {
    echo "✅ File routes/api.php ada\n\n";
    
    // Cek apakah route monthly-table ada di file
    $content = file_get_contents($routesFile);
    if (strpos($content, 'monthly-table') !== false) {
        echo "✅ Route monthly-table ditemukan di file\n\n";
        
        // Tampilkan line yang mengandung monthly-table
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'monthly-table') !== false) {
                echo "📄 Line " . ($lineNum + 1) . ": " . trim($line) . "\n";
            }
        }
    } else {
        echo "❌ Route monthly-table TIDAK ditemukan di file\n";
        echo "File perlu di-update!\n";
    }
} else {
    echo "❌ File routes/api.php TIDAK ada\n";
}

echo "\n=== CHECK CONTROLLER ===\n";

// Cek apakah controller ada
$controllerFile = 'app/Http/Controllers/AttendanceExportController.php';
if (file_exists($controllerFile)) {
    echo "✅ Controller AttendanceExportController.php ada\n";
    
    // Cek apakah method monthlyTable ada
    $content = file_get_contents($controllerFile);
    if (strpos($content, 'monthlyTable') !== false) {
        echo "✅ Method monthlyTable ditemukan\n";
    } else {
        echo "❌ Method monthlyTable TIDAK ditemukan\n";
    }
} else {
    echo "❌ Controller AttendanceExportController.php TIDAK ada\n";
}

echo "\n=== SELESAI ===\n"; 