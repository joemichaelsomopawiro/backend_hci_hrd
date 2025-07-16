<?php
/**
 * Test Download File Excel
 * Script untuk menguji endpoint download file Excel
 */

// Konfigurasi
$baseUrl = 'http://127.0.0.1:8000/api';
$filename = 'Absensi_July_2025_Hope_Channel_Indonesia.xls';

echo "=== TEST DOWNLOAD FILE EXCEL ===\n";
echo "Base URL: {$baseUrl}\n";
echo "Filename: {$filename}\n\n";

// Test 1: Cek apakah file ada di storage
echo "1. Mengecek keberadaan file di storage...\n";
$storagePath = __DIR__ . '/storage/app/public/exports/' . $filename;
if (file_exists($storagePath)) {
    echo "✓ File ditemukan di storage\n";
    echo "  Path: {$storagePath}\n";
    echo "  Size: " . number_format(filesize($storagePath)) . " bytes\n";
} else {
    echo "✗ File TIDAK ditemukan di storage\n";
    echo "  Path: {$storagePath}\n";
}

echo "\n";

// Test 2: Test endpoint download
echo "2. Menguji endpoint download...\n";
$downloadUrl = "{$baseUrl}/attendance/export/download/{$filename}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Headers:\n";
echo $headers . "\n";

if ($httpCode === 200) {
    echo "✓ Endpoint download berfungsi dengan baik\n";
    
    // Parse headers untuk mendapatkan info file
    $headerLines = explode("\n", $headers);
    foreach ($headerLines as $line) {
        if (stripos($line, 'Content-Disposition') !== false) {
            echo "  Content-Disposition: " . trim($line) . "\n";
        }
        if (stripos($line, 'Content-Type') !== false) {
            echo "  Content-Type: " . trim($line) . "\n";
        }
        if (stripos($line, 'Content-Length') !== false) {
            echo "  Content-Length: " . trim($line) . "\n";
        }
    }
} else {
    echo "✗ Endpoint download gagal (HTTP {$httpCode})\n";
    echo "Response body:\n";
    echo $body . "\n";
}

echo "\n";

// Test 3: Test download file lengkap
echo "3. Menguji download file lengkap...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$fileContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

curl_close($ch);

if ($httpCode === 200 && !empty($fileContent)) {
    echo "✓ Download file berhasil\n";
    echo "  Content Type: {$contentType}\n";
    echo "  Content Length: " . number_format($contentLength) . " bytes\n";
    echo "  File content length: " . number_format(strlen($fileContent)) . " bytes\n";
    
    // Simpan file untuk testing
    $testFile = __DIR__ . '/test_downloaded_file.xls';
    file_put_contents($testFile, $fileContent);
    echo "  File disimpan sebagai: {$testFile}\n";
    
    // Cek apakah file Excel valid
    if (strpos($fileContent, '<html') !== false || strpos($fileContent, '<!DOCTYPE') !== false) {
        echo "✓ File berisi HTML (format Excel yang benar)\n";
    } else {
        echo "⚠ File tidak berisi HTML, mungkin bukan format Excel yang diharapkan\n";
    }
} else {
    echo "✗ Download file gagal\n";
    echo "  HTTP Code: {$httpCode}\n";
    echo "  Content Length: {$contentLength}\n";
}

echo "\n";

// Test 4: Cek storage link
echo "4. Mengecek storage link...\n";
$publicPath = __DIR__ . '/public/storage/exports/' . $filename;
if (file_exists($publicPath)) {
    echo "✓ Storage link berfungsi\n";
    echo "  Public path: {$publicPath}\n";
    echo "  Size: " . number_format(filesize($publicPath)) . " bytes\n";
} else {
    echo "✗ Storage link TIDAK berfungsi\n";
    echo "  Public path: {$publicPath}\n";
    echo "  Saran: Jalankan 'php artisan storage:link'\n";
}

echo "\n";

// Test 5: Test URL langsung
echo "5. Menguji URL langsung ke file...\n";
$directUrl = "http://127.0.0.1:8000/storage/exports/{$filename}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $directUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($httpCode === 200) {
    echo "✓ URL langsung berfungsi (HTTP {$httpCode})\n";
    echo "  URL: {$directUrl}\n";
} else {
    echo "✗ URL langsung tidak berfungsi (HTTP {$httpCode})\n";
    echo "  URL: {$directUrl}\n";
}

echo "\n=== SELESAI ===\n"; 