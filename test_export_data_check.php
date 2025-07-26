<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 CHECKING EXPORT DATA\n";
echo "========================\n\n";

// 1. Cek data employee_attendance
echo "1. EMPLOYEE_ATTENDANCE TABLE:\n";
$employeeAttendances = DB::table('employee_attendance')
    ->where('is_active', true)
    ->get();

echo "Total active employees: " . $employeeAttendances->count() . "\n";
if ($employeeAttendances->count() > 0) {
    echo "Sample data:\n";
    foreach ($employeeAttendances->take(5) as $emp) {
        echo "- PIN: {$emp->machine_user_id}, Name: {$emp->name}, Employee ID: {$emp->employee_id}\n";
    }
} else {
    echo "❌ NO ACTIVE EMPLOYEES FOUND!\n";
}
echo "\n";

// 2. Cek data attendance hari ini
echo "2. ATTENDANCE TABLE (TODAY):\n";
$today = Carbon::today()->format('Y-m-d');
$todayAttendances = DB::table('attendances')
    ->where('date', $today)
    ->get();

echo "Total attendance today ({$today}): " . $todayAttendances->count() . "\n";
if ($todayAttendances->count() > 0) {
    echo "Sample data:\n";
    foreach ($todayAttendances->take(5) as $att) {
        echo "- PIN: {$att->user_pin}, Name: {$att->user_name}, Status: {$att->status}\n";
    }
} else {
    echo "❌ NO ATTENDANCE DATA FOR TODAY!\n";
}
echo "\n";

// 3. Cek data attendance bulan ini
echo "3. ATTENDANCE TABLE (THIS MONTH):\n";
$currentMonth = Carbon::now()->month;
$currentYear = Carbon::now()->year;
$monthlyAttendances = DB::table('attendances')
    ->whereYear('date', $currentYear)
    ->whereMonth('date', $currentMonth)
    ->get();

echo "Total attendance this month ({$currentMonth}/{$currentYear}): " . $monthlyAttendances->count() . "\n";
if ($monthlyAttendances->count() > 0) {
    echo "Sample data:\n";
    foreach ($monthlyAttendances->take(5) as $att) {
        echo "- PIN: {$att->user_pin}, Name: {$att->user_name}, Date: {$att->date}, Status: {$att->status}\n";
    }
} else {
    echo "❌ NO ATTENDANCE DATA FOR THIS MONTH!\n";
}
echo "\n";

// 4. Cek mismatch user_pin
echo "4. CHECKING USER_PIN MATCH:\n";
$activePins = $employeeAttendances->pluck('machine_user_id')->toArray();
$attendancePins = $monthlyAttendances->pluck('user_pin')->unique()->toArray();

$matchingPins = array_intersect($activePins, $attendancePins);
$onlyInAttendance = array_diff($attendancePins, $activePins);
$onlyInEmployee = array_diff($activePins, $attendancePins);

echo "Active employee PINs: " . count($activePins) . "\n";
echo "Attendance PINs: " . count($attendancePins) . "\n";
echo "Matching PINs: " . count($matchingPins) . "\n";
echo "Only in attendance: " . count($onlyInAttendance) . "\n";
echo "Only in employee: " . count($onlyInEmployee) . "\n";

if (!empty($onlyInAttendance)) {
    echo "PINs only in attendance: " . implode(', ', $onlyInAttendance) . "\n";
}
if (!empty($onlyInEmployee)) {
    echo "PINs only in employee: " . implode(', ', $onlyInEmployee) . "\n";
}
echo "\n";

// 5. Test export logic
echo "5. TESTING EXPORT LOGIC:\n";

// Test daily export
$dailyData = DB::table('attendances')
    ->where('date', $today)
    ->get();

$registeredEmployees = DB::table('employee_attendance')
    ->where('is_active', true)
    ->get();

$exportData = [];

// Add attendance data
foreach ($dailyData as $attendance) {
    $employee = $registeredEmployees->where('machine_user_id', $attendance->user_pin)->first();
    $exportData[] = [
        'employee_id' => $employee ? $employee->employee_id : null,
        'nama' => $employee ? $employee->name : $attendance->user_name,
        'user_pin' => $attendance->user_pin,
        'status' => $attendance->status
    ];
}

// Add absent employees
foreach ($registeredEmployees as $emp) {
    $hasAttendance = $dailyData->where('user_pin', $emp->machine_user_id)->count() > 0;
    if (!$hasAttendance) {
        $exportData[] = [
            'employee_id' => $emp->employee_id,
            'nama' => $emp->name,
            'user_pin' => $emp->machine_user_id,
            'status' => 'absent'
        ];
    }
}

echo "Daily export would contain: " . count($exportData) . " records\n";
if (count($exportData) > 0) {
    echo "Sample export data:\n";
    foreach (array_slice($exportData, 0, 5) as $record) {
        echo "- {$record['nama']} (PIN: {$record['user_pin']}) - {$record['status']}\n";
    }
} else {
    echo "❌ NO DATA FOR EXPORT!\n";
}

echo "\n";
echo "🎯 RECOMMENDATIONS:\n";
echo "==================\n";

if ($employeeAttendances->count() == 0) {
    echo "1. ❌ Register employees in employee_attendance table\n";
}

if ($todayAttendances->count() == 0) {
    echo "2. ❌ Sync attendance data from machine or upload TXT file\n";
}

if (count($matchingPins) == 0 && $employeeAttendances->count() > 0 && $monthlyAttendances->count() > 0) {
    echo "3. ❌ Fix user_pin mismatch between tables\n";
}

if (count($exportData) > 0) {
    echo "✅ Export should work with " . count($exportData) . " records\n";
} else {
    echo "❌ Export will be empty - need to populate data first\n";
}

echo "\n";
?> 