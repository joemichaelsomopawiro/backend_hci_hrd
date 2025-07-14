<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NationalHoliday;

echo "=== Hari Libur Terbaru ===\n";

// Ambil 10 data terbaru
$recentHolidays = NationalHoliday::orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'date', 'name', 'type', 'created_at', 'created_by']);

if ($recentHolidays->count() > 0) {
    foreach ($recentHolidays as $holiday) {
        echo "ID: {$holiday->id} | {$holiday->date} | {$holiday->name} | {$holiday->type} | Created: {$holiday->created_at}\n";
    }
} else {
    echo "Tidak ada data hari libur\n";
}

echo "\n=== Cek Tanggal Spesifik ===\n";
$testDate = '2025-07-14';
$specificHoliday = NationalHoliday::where('date', $testDate)->first();

if ($specificHoliday) {
    echo "✅ Tanggal {$testDate} ada:\n";
    echo "   ID: {$specificHoliday->id}\n";
    echo "   Nama: {$specificHoliday->name}\n";
    echo "   Tipe: {$specificHoliday->type}\n";
    echo "   Created: {$specificHoliday->created_at}\n";
} else {
    echo "❌ Tanggal {$testDate} tidak ada\n";
}

echo "\n=== Total Data: " . NationalHoliday::count() . " ===\n"; 