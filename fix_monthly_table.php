<?php
// Script untuk memperbaiki masalah monthly table
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use Carbon\Carbon;

echo "=== FIXING MONTHLY TABLE ===\n\n";

// 1. Check current data
echo "1. CHECKING CURRENT DATA:\n";
$totalRecords = Attendance::count();
echo "Total attendance records: $totalRecords\n";

$uniqueUserPins = Attendance::distinct('user_pin')->pluck('user_pin')->toArray();
echo "Unique user_pins: " . count($uniqueUserPins) . "\n";
echo "User PINs: " . implode(', ', array_map(function($pin) { return "'$pin'"; }, $uniqueUserPins)) . "\n";

echo "\n";

// 2. Check July 2025 data
echo "2. CHECKING JULY 2025 DATA:\n";
$julyRecords = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->get();
echo "July 2025 records: " . $julyRecords->count() . "\n";

if ($julyRecords->count() > 0) {
    echo "Sample July records:\n";
    foreach ($julyRecords->take(5) as $record) {
        echo "- ID: {$record->id}, PIN: '{$record->user_pin}', Name: {$record->user_name}, Date: {$record->date}\n";
    }
} else {
    echo "❌ No July 2025 records found!\n";
}

echo "\n";

// 3. Check if there are records with empty user_pin
echo "3. CHECKING EMPTY USER_PIN:\n";
$emptyPinRecords = Attendance::whereNull('user_pin')->orWhere('user_pin', '')->get();
echo "Records with empty user_pin: " . $emptyPinRecords->count() . "\n";

if ($emptyPinRecords->count() > 0) {
    echo "Sample empty PIN records:\n";
    foreach ($emptyPinRecords->take(3) as $record) {
        echo "- ID: {$record->id}, Name: {$record->user_name}, Date: {$record->date}\n";
    }
}

echo "\n";

// 4. Check all unique user_names
echo "4. CHECKING UNIQUE USER_NAMES:\n";
$uniqueUserNames = Attendance::distinct('user_name')->pluck('user_name')->toArray();
echo "Unique user_names: " . count($uniqueUserNames) . "\n";
echo "User Names: " . implode(', ', array_map(function($name) { return "'$name'"; }, $uniqueUserNames)) . "\n";

echo "\n";

// 5. Simulate the monthly table logic
echo "5. SIMULATING MONTHLY TABLE LOGIC:\n";

$month = 7;
$year = 2025;

// Get all employees from attendance table
$allEmployees = Attendance::select('user_pin', 'user_name', 'card_number')
    ->distinct()
    ->orderBy('user_name')
    ->get()
    ->keyBy('user_pin');

echo "Employees from attendance table: " . $allEmployees->count() . "\n";

// Get attendance data for July 2025
$julyAttendances = Attendance::whereYear('date', $year)->whereMonth('date', $month)->get();
$grouped = $julyAttendances->groupBy('user_pin');

echo "Attendance records for July 2025: " . $julyAttendances->count() . "\n";
echo "Unique user_pins in July attendance: " . $grouped->count() . "\n";

// Generate working days
$workingDays = [];
$startDate = Carbon::create($year, $month, 1);
$endDate = Carbon::create($year, $month, 1)->endOfMonth();
$currentDate = $startDate->copy();

while ($currentDate <= $endDate) {
    if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
        $workingDays[] = [
            'day' => $currentDate->day,
            'date' => $currentDate->format('Y-m-d'),
            'dayName' => $currentDate->format('D')
        ];
    }
    $currentDate->addDay();
}

echo "Working days: " . count($workingDays) . "\n";

// Process table data
$tableData = [];
foreach ($allEmployees as $userPin => $employee) {
    $userAttendances = $grouped->get($userPin, collect());
    $row = [
        'user_pin' => $userPin,
        'nama' => $employee->user_name,
        'card_number' => $employee->card_number,
        'total_hadir' => 0,
        'total_jam_kerja' => 0,
        'total_absen' => 0,
        'daily_data' => []
    ];
    
    foreach ($workingDays as $workingDay) {
        $date = $workingDay['date'];
        $att = $userAttendances->where('date', $date)->first();
        if ($att) {
            if (in_array($att->status, ['on_leave', 'sick_leave'])) {
                $row['daily_data'][$workingDay['day']] = [
                    'status' => 'cuti',
                    'type' => $att->status === 'sick_leave' ? 'Sakit' : 'Cuti',
                    'check_in' => null,
                    'check_out' => null,
                    'work_hours' => 0
                ];
                $row['total_hadir']++;
            } elseif ($att->check_in) {
                $row['daily_data'][$workingDay['day']] = [
                    'status' => $att->status,
                    'type' => $att->status === 'present_late' ? 'Terlambat' : 'Hadir',
                    'check_in' => $att->check_in ? $att->check_in->format('H:i:s') : null,
                    'check_out' => $att->check_out ? $att->check_out->format('H:i:s') : null,
                    'work_hours' => $att->work_hours ?? 0
                ];
                $row['total_hadir']++;
                $row['total_jam_kerja'] += $att->work_hours ?? 0;
            } else {
                $row['daily_data'][$workingDay['day']] = [
                    'status' => 'absent',
                    'type' => 'Absen',
                    'check_in' => null,
                    'check_out' => null,
                    'work_hours' => 0
                ];
                $row['total_absen']++;
            }
        } else {
            $row['daily_data'][$workingDay['day']] = [
                'status' => 'no_data',
                'type' => 'Tidak Ada Data',
                'check_in' => null,
                'check_out' => null,
                'work_hours' => 0
            ];
            $row['total_absen']++;
        }
    }
    $tableData[] = $row;
}

echo "Final table data records: " . count($tableData) . "\n";

if (count($tableData) > 0) {
    echo "Sample table data:\n";
    foreach (array_slice($tableData, 0, 3) as $index => $record) {
        echo ($index + 1) . ". {$record['nama']} (PIN: '{$record['user_pin']}')\n";
        echo "   Total Hadir: {$record['total_hadir']}\n";
        echo "   Total Jam Kerja: {$record['total_jam_kerja']}\n";
        echo "   Total Absen: {$record['total_absen']}\n";
        echo "   Daily Data Keys: " . implode(', ', array_keys($record['daily_data'])) . "\n";
        echo "\n";
    }
}

echo "\n✅ Analysis completed!\n"; 