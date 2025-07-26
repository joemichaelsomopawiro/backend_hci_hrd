<?php
// Script untuk menambahkan data test ke database
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use Carbon\Carbon;

echo "=== ADDING TEST DATA ===\n\n";

// Data test karyawan
$testEmployees = [
    ['user_pin' => '1001', 'user_name' => 'John Doe', 'card_number' => 'CARD001'],
    ['user_pin' => '1002', 'user_name' => 'Jane Smith', 'card_number' => 'CARD002'],
    ['user_pin' => '1003', 'user_name' => 'Bob Johnson', 'card_number' => 'CARD003'],
    ['user_pin' => '1004', 'user_name' => 'Alice Brown', 'card_number' => 'CARD004'],
    ['user_pin' => '1005', 'user_name' => 'Charlie Wilson', 'card_number' => 'CARD005'],
];

// Tambahkan data attendance untuk Juli 2025
$month = 7;
$year = 2025;
$startDate = Carbon::create($year, $month, 1);
$endDate = Carbon::create($year, $month, 1)->endOfMonth();

echo "Adding attendance data for July 2025...\n";

$addedCount = 0;
$currentDate = $startDate->copy();

while ($currentDate <= $endDate) {
    // Hanya hari kerja (Senin-Jumat)
    if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
        foreach ($testEmployees as $employee) {
            // Random attendance status
            $statuses = ['present_ontime', 'present_late', 'absent'];
            $status = $statuses[array_rand($statuses)];
            
            $attendanceData = [
                'user_pin' => $employee['user_pin'],
                'user_name' => $employee['user_name'],
                'card_number' => $employee['card_number'],
                'date' => $currentDate->format('Y-m-d'),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Add check_in and check_out times if present
            if ($status !== 'absent') {
                $attendanceData['check_in'] = $status === 'present_ontime' ? '08:00:00' : '08:30:00';
                $attendanceData['check_out'] = '17:00:00';
                $attendanceData['work_hours'] = $status === 'present_ontime' ? 8.0 : 7.5;
            }
            
            // Check if record already exists
            $existing = Attendance::where('user_pin', $employee['user_pin'])
                ->where('date', $currentDate->format('Y-m-d'))
                ->first();
            
            if (!$existing) {
                Attendance::create($attendanceData);
                $addedCount++;
            }
        }
    }
    $currentDate->addDay();
}

echo "Added $addedCount attendance records\n";

// Verify data
echo "\n=== VERIFYING DATA ===\n";
$totalRecords = Attendance::count();
echo "Total attendance records: $totalRecords\n";

$julyRecords = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->count();
echo "July 2025 attendance records: $julyRecords\n";

$uniqueUserPins = Attendance::distinct('user_pin')->count();
echo "Unique user_pins: $uniqueUserPins\n";

$uniqueUserPinsJuly = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->distinct('user_pin')->count();
echo "Unique user_pins in July 2025: $uniqueUserPinsJuly\n";

echo "\n✅ Test data added successfully!\n"; 