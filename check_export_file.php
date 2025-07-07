<?php
/**
 * Script untuk mengecek isi file export
 */

echo "🔍 MENGE CEK FILE EXPORT\n";
echo "========================\n\n";

// Cek file Excel
$excelFile = 'storage/app/public/exports/Absensi_July_2025_Hope_Channel_Indonesia.xls';

if (file_exists($excelFile)) {
    echo "📁 File Excel ditemukan: " . $excelFile . "\n";
    echo "📊 Ukuran file: " . filesize($excelFile) . " bytes\n\n";
    
    // Baca isi file
    $content = file_get_contents($excelFile);
    
    // Cari bagian table body
    if (strpos($content, '<tbody>') !== false) {
        echo "✅ File berisi HTML table\n";
        
        // Cari data dalam table
        if (strpos($content, '08:00') !== false || strpos($content, '07:00') !== false || strpos($content, '06:00') !== false) {
            echo "✅ Ditemukan data jam absen dalam file\n";
        } else {
            echo "❌ Tidak ditemukan data jam absen dalam file\n";
        }
        
        // Cari nama karyawan
        if (strpos($content, 'Jeri Januar Salle') !== false || strpos($content, 'Edward Ridley Tauran') !== false) {
            echo "✅ Ditemukan nama karyawan dalam file\n";
        } else {
            echo "❌ Tidak ditemukan nama karyawan dalam file\n";
        }
        
    } else {
        echo "❌ File tidak berisi HTML table\n";
    }
    
    // Tampilkan sample isi file (1000 karakter pertama)
    echo "\n📋 Sample isi file (1000 karakter pertama):\n";
    echo "----------------------------------------\n";
    echo substr($content, 0, 1000) . "\n";
    echo "----------------------------------------\n";
    
} else {
    echo "❌ File Excel tidak ditemukan: " . $excelFile . "\n";
}

echo "\n";

// Cek file CSV juga
$csvFile = 'storage/app/public/exports/Absensi_July_2025_Hope_Channel_Indonesia.csv';

if (file_exists($csvFile)) {
    echo "📁 File CSV ditemukan: " . $csvFile . "\n";
    echo "📊 Ukuran file: " . filesize($csvFile) . " bytes\n\n";
    
    // Baca isi file CSV
    $csvContent = file_get_contents($csvFile);
    
    // Tampilkan sample isi file CSV
    echo "📋 Sample isi file CSV (500 karakter pertama):\n";
    echo "----------------------------------------\n";
    echo substr($csvContent, 0, 500) . "\n";
    echo "----------------------------------------\n";
    
} else {
    echo "❌ File CSV tidak ditemukan: " . $csvFile . "\n";
}

echo "\n✅ Pengecekan selesai!\n"; 