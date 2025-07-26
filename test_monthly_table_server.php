<?php
// Test script untuk server production
// Jalankan via SSH: php test_monthly_table_server.php

echo "=== TEST MONTHLY TABLE SERVER ===\n\n";

// Cek apakah ada data di tabel attendance Juli 2025
try {
    // Koneksi database sesuai environment server
    $host = 'localhost';
    $dbname = 'u858985646_hci';
    $username = 'u858985646_hci';
    $password = '|LlK/g6Ba';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Koneksi database berhasil\n\n";
    
    // Cek data attendance Juli 2025
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'];
    
    echo "📊 Total data attendance Juli 2025: $total\n\n";
    
    if ($total > 0) {
        // Ambil sample data
        $stmt = $pdo->prepare("SELECT date, user_name, user_pin, status FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7 LIMIT 5");
        $stmt->execute();
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📋 Sample data:\n";
        foreach ($samples as $sample) {
            echo "- Date: {$sample['date']}, User: {$sample['user_name']}, PIN: {$sample['user_pin']}, Status: {$sample['status']}\n";
        }
        
        // Cek unique user_pin
        $stmt = $pdo->prepare("SELECT DISTINCT user_pin FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7");
        $stmt->execute();
        $userPins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\n👥 Unique user_pin: " . count($userPins) . "\n";
        echo "User PINs: " . implode(', ', $userPins) . "\n";
        
    } else {
        echo "❌ Tidak ada data attendance untuk Juli 2025!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error database: " . $e->getMessage() . "\n";
}

echo "\n=== TEST ROUTE CACHE ===\n";

// Clear route cache
echo "🔄 Clearing route cache...\n";
$output = shell_exec('php artisan route:clear 2>&1');
echo "Output: " . trim($output) . "\n";

// Cache route
echo "🔄 Caching routes...\n";
$output = shell_exec('php artisan route:cache 2>&1');
echo "Output: " . trim($output) . "\n";

// Test route list
echo "🔍 Checking route list...\n";
$routeOutput = shell_exec('php artisan route:list | grep monthly-table 2>&1');
echo "Monthly table route:\n";
echo $routeOutput;

echo "\n=== SELESAI ===\n"; 