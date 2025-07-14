<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NationalHoliday;

$testDate = '2025-07-14';

echo "=== Hapus Data Lama: {$testDate} ===\n";

// Cari data yang akan dihapus
$existingHoliday = NationalHoliday::where('date', $testDate)->first();

if ($existingHoliday) {
    echo "Data yang akan dihapus:\n";
    echo "   ID: {$existingHoliday->id}\n";
    echo "   Nama: {$existingHoliday->name}\n";
    echo "   Tipe: {$existingHoliday->type}\n";
    echo "   Created: {$existingHoliday->created_at}\n";
    
    // Hapus data
    $existingHoliday->delete();
    echo "✅ Data berhasil dihapus\n";
} else {
    echo "❌ Data tidak ditemukan\n";
}

// Verifikasi penghapusan
echo "\n=== Verifikasi Setelah Hapus ===\n";
$checkHoliday = NationalHoliday::where('date', $testDate)->first();

if ($checkHoliday) {
    echo "❌ Data masih ada\n";
} else {
    echo "✅ Data berhasil dihapus, tanggal {$testDate} sekarang bisa ditambahkan\n";
} 