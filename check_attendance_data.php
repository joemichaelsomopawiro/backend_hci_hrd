<?php
// Script untuk memeriksa data attendance
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use Carbon\Carbon;

echo "=== CHECK ATTENDANCE DATA ===\n\n";

// Total records
$totalRecords = Attendance::count();
echo "Total attendance records: $totalRecords\n";

// Records in July 2025
$julyRecords = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->count();
echo "Attendance in July 2025: $julyRecords\n";

// Unique user_pins
$uniquePins = Attendance::distinct('user_pin')->count();
echo "Unique user_pins (total): $uniquePins\n";

// Unique user_pins in July 2025
$julyUniquePins = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->distinct('user_pin')->count();
echo "Unique user_pins in July 2025: $julyUniquePins\n";

// Sample data
echo "\n=== SAMPLE DATA ===\n";
$sampleData = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->limit(5)->get();
foreach ($sampleData as $record) {
    echo "ID: {$record->id}, User: {$record->user_name}, PIN: {$record->user_pin}, Date: {$record->date}, Status: {$record->status}\n";
}

// Check all unique user_pins
echo "\n=== ALL UNIQUE USER PINS ===\n";
$allPins = Attendance::select('user_pin', 'user_name')->distinct()->get();
foreach ($allPins as $pin) {
    echo "PIN: {$pin->user_pin}, Name: {$pin->user_name}\n";
}

echo "\n=== JULY 2025 UNIQUE USER PINS ===\n";
$julyPins = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->select('user_pin', 'user_name')->distinct()->get();
foreach ($julyPins as $pin) {
    echo "PIN: {$pin->user_pin}, Name: {$pin->user_name}\n";
} 