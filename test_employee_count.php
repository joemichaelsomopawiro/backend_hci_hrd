<?php
// Test script untuk verifikasi jumlah karyawan di database
// Jalankan via SSH: php test_employee_count.php

echo "=== TEST EMPLOYEE COUNT ===\n\n";

// Koneksi database sesuai environment server
$host = 'localhost';
$dbname = 'u858985646_hci';
$username = 'u858985646_hci';
$password = '|LlK/g6Ba';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Koneksi database berhasil\n\n";
    
    // Cek total data attendance
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendances");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total data attendance: " . $result['total'] . "\n";
    
    // Cek unique user_pin
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_pin) as total FROM attendances");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "👥 Total unique user_pin: " . $result['total'] . "\n";
    
    // Cek data Juli 2025
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📅 Total data Juli 2025: " . $result['total'] . "\n";
    
    // Cek unique user_pin Juli 2025
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_pin) as total FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "👥 Unique user_pin Juli 2025: " . $result['total'] . "\n";
    
    // Tampilkan semua user_pin yang ada
    echo "\n📋 Semua user_pin yang ada:\n";
    $stmt = $pdo->prepare("SELECT DISTINCT user_pin, user_name FROM attendances ORDER BY user_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "- {$user['user_name']} (PIN: {$user['user_pin']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error database: " . $e->getMessage() . "\n";
}

echo "\n=== SELESAI ===\n"; 