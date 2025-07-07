<?php
/**
 * Script untuk debug format tanggal di database
 */

// Load Laravel
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use Carbon\Carbon;

echo "🔍 DEBUG FORMAT TANGGAL\n";
echo "=======================\n\n";

// Ambil sample data attendance
$attendances = Attendance::whereYear('date', 2025)
    ->whereMonth('date', 7)
    ->take(10)
    ->get();

echo "📊 Sample Data Attendance:\n";
foreach ($attendances as $att) {
    echo "- User PIN: {$att->user_pin}\n";
    echo "  Date (raw): {$att->date}\n";
    echo "  Date (formatted): " . $att->date->format('Y-m-d') . "\n";
    echo "  Check In: " . ($att->check_in ? $att->check_in->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "  Check Out: " . ($att->check_out ? $att->check_out->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "\n";
}

// Cek format tanggal yang dicari
echo "📅 Format Tanggal yang Dicari:\n";
$workingDays = [
    ['date' => '2025-07-01', 'day' => 1],
    ['date' => '2025-07-02', 'day' => 2],
    ['date' => '2025-07-03', 'day' => 3],
    ['date' => '2025-07-04', 'day' => 4],
    ['date' => '2025-07-07', 'day' => 7]
];

foreach ($workingDays as $day) {
    $count = Attendance::where('date', $day['date'])->count();
    echo "- {$day['date']}: {$count} records\n";
}

echo "\n";

// Cek format tanggal dengan Carbon
echo "📅 Cek dengan Carbon:\n";
foreach ($workingDays as $day) {
    $carbonDate = Carbon::parse($day['date']);
    $count = Attendance::whereDate('date', $carbonDate)->count();
    echo "- {$day['date']} (Carbon): {$count} records\n";
}

echo "\n✅ Debug selesai!\n"; 