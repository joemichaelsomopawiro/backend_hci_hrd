<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NationalHoliday;

$testDate = '2025-07-14'; // Tanggal yang user coba tambahkan

echo "=== Cek Tanggal: {$testDate} ===\n";

// Cek apakah tanggal sudah ada
$existingHoliday = NationalHoliday::where('date', $testDate)->first();

if ($existingHoliday) {
    echo "❌ Tanggal {$testDate} SUDAH ADA di database:\n";
    echo "   ID: {$existingHoliday->id}\n";
    echo "   Nama: {$existingHoliday->name}\n";
    echo "   Tipe: {$existingHoliday->type}\n";
    echo "   Created: {$existingHoliday->created_at}\n";
} else {
    echo "✅ Tanggal {$testDate} BELUM ADA di database\n";
    echo "   Bisa ditambahkan\n";
}

// Cek semua data yang ada untuk memastikan
echo "\n=== Semua Data Hari Libur ===\n";
$allHolidays = NationalHoliday::orderBy('date')->get(['id', 'date', 'name', 'type']);

if ($allHolidays->count() > 0) {
    foreach ($allHolidays as $holiday) {
        echo "{$holiday->date} - {$holiday->name} ({$holiday->type})\n";
    }
} else {
    echo "Tidak ada data hari libur\n";
}

echo "\n=== Total: " . $allHolidays->count() . " ===\n"; 