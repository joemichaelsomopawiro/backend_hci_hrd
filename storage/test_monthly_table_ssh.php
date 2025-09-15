<?php
// Test script untuk cek data monthly table
// Jalankan via SSH: php test_monthly_table_ssh.php

echo "=== TEST MONTHLY TABLE DATA ===\n\n";

// Cek apakah ada data di tabel attendance Juli 2025
try {
    // Koneksi database langsung
    $host = 'localhost';
    $dbname = 'backend_hci';
    $username = 'root';
    $password = '';
    
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
        
        // Cek tanggal yang ada
        $stmt = $pdo->prepare("SELECT DISTINCT date FROM attendances WHERE YEAR(date) = 2025 AND MONTH(date) = 7 ORDER BY date");
        $stmt->execute();
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\n📅 Tanggal yang ada: " . implode(', ', $dates) . "\n";
        
    } else {
        echo "❌ Tidak ada data attendance untuk Juli 2025!\n";
    }
    
    // Cek data employee_attendance
    echo "\n=== Cek tabel employee_attendance ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM employee_attendance WHERE YEAR(date) = 2025 AND MONTH(date) = 7");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalEA = $result['total'];
    echo "📊 Data employee_attendance Juli 2025: $totalEA\n";
    
    // Test working days Juli 2025
    echo "\n=== Test working days Juli 2025 ===\n";
    $workingDays = [];
    $year = 2025;
    $month = 7;
    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
        $dayOfWeek = date('N', mktime(0, 0, 0, $month, $day, $year)); // 1=Monday, 7=Sunday
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
            $workingDays[] = [
                'day' => $day,
                'date' => $date,
                'day_name' => date('l', mktime(0, 0, 0, $month, $day, $year))
            ];
        }
    }
    
    echo "📅 Working days Juli 2025: " . count($workingDays) . " hari\n";
    foreach ($workingDays as $day) {
        echo "- {$day['day']} ({$day['day_name']}): {$day['date']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SELESAI ===\n"; 