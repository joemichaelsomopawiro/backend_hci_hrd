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
$testData = [
    'date' => '2025-07-14',
    'name' => 'Test Holiday Final',
    'description' => 'Test description final',
    'type' => 'custom'
];

echo "\n=== Testing Holiday Addition ===\n";
echo "Data yang akan ditambahkan: " . json_encode($testData) . "\n";

// Cek apakah sudah ada
$existing = NationalHoliday::where('date', $testData['date'])->first();
if ($existing) {
    echo "❌ Tanggal {$testData['date']} masih ada: {$existing->name}\n";
    exit;
}

// Coba tambah
try {
    $holiday = NationalHoliday::create([
        'date' => $testData['date'],
        'name' => $testData['name'],
        'description' => $testData['description'],
        'type' => $testData['type'],
        'created_by' => $hrUser->id
    ]);
    
    echo "✅ Berhasil menambah: {$holiday->date} - {$holiday->name}\n";
    echo "   ID: {$holiday->id}\n";
    echo "   Created: {$holiday->created_at}\n";
    
    // Hapus untuk test berikutnya
    $holiday->delete();
    echo "🗑️  Dihapus untuk testing\n";
    
} catch (Exception $e) {
    echo "❌ Gagal menambah: " . $e->getMessage() . "\n";
}

echo "\n=== Backend siap untuk frontend ===\n"; 