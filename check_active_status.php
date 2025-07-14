<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NationalHoliday;

echo "=== Cek Status is_active ===\n";

// Cek data terbaru
$recentHolidays = NationalHoliday::orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['id', 'date', 'name', 'type', 'is_active', 'created_at']);

foreach ($recentHolidays as $holiday) {
    $status = $holiday->is_active ? '✅ AKTIF' : '❌ TIDAK AKTIF';
    echo "ID: {$holiday->id} | {$holiday->date} | {$holiday->name} | {$holiday->type} | {$status}\n";
}

echo "\n=== Cek Data Aktif Juli 2025 ===\n";
$julyHolidays = NationalHoliday::active()
    ->byMonth(2025, 7)
    ->orderBy('date')
    ->get(['id', 'date', 'name', 'type', 'is_active']);

if ($julyHolidays->count() > 0) {
    foreach ($julyHolidays as $holiday) {
        echo "{$holiday->date} - {$holiday->name} ({$holiday->type})\n";
    }
} else {
    echo "Tidak ada hari libur aktif di Juli 2025\n";
}

echo "\n=== Cek Semua Data Juli 2025 (Termasuk Tidak Aktif) ===\n";
$allJulyHolidays = NationalHoliday::byMonth(2025, 7)
    ->orderBy('date')
    ->get(['id', 'date', 'name', 'type', 'is_active']);

if ($allJulyHolidays->count() > 0) {
    foreach ($allJulyHolidays as $holiday) {
        $status = $holiday->is_active ? 'AKTIF' : 'TIDAK AKTIF';
        echo "{$holiday->date} - {$holiday->name} ({$holiday->type}) - {$status}\n";
    }
} else {
    echo "Tidak ada hari libur di Juli 2025\n";
} 