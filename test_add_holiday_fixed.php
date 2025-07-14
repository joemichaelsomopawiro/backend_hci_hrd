<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NationalHoliday;
use App\Models\User;

// Cari user HR
$hrUser = User::where('role', 'HR')->first();

if (!$hrUser) {
    echo "❌ Tidak ada user dengan role HR\n";
    exit;
}

echo "✅ Menggunakan user HR: {$hrUser->name} (ID: {$hrUser->id})\n";

// Test data
$testDates = [
    '2025-01-15',
    '2025-01-16',
    '2025-02-15'
];

echo "\n=== Testing Holiday Addition ===\n";

foreach ($testDates as $date) {
    echo "\n--- Testing date: {$date} ---\n";
    
    // Cek apakah sudah ada
    $existing = NationalHoliday::where('date', $date)->first();
    if ($existing) {
        echo "❌ Tanggal {$date} sudah ada: {$existing->name} ({$existing->type})\n";
        continue;
    }
    
    // Coba tambah
    try {
        $holiday = NationalHoliday::create([
            'date' => $date,
            'name' => 'Test Holiday ' . $date,
            'description' => 'Test description',
            'type' => 'custom',
            'created_by' => $hrUser->id
        ]);
        
        echo "✅ Berhasil menambah: {$holiday->date} - {$holiday->name}\n";
        
        // Hapus untuk test berikutnya
        $holiday->delete();
        echo "🗑️  Dihapus untuk testing\n";
        
    } catch (Exception $e) {
        echo "❌ Gagal menambah: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Selesai Testing ===\n"; 