<?php
// Script untuk memeriksa data employee attendance
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EmployeeAttendance;
use App\Models\Employee;
use App\Models\Attendance;

echo "=== CHECK EMPLOYEE DATA ===\n\n";

// Total employee attendance records
$totalEmployeeAttendance = EmployeeAttendance::count();
echo "Total employee attendance records: $totalEmployeeAttendance\n";

// Active employee attendance records
$activeEmployeeAttendance = EmployeeAttendance::where('is_active', true)->count();
echo "Active employee attendance records: $activeEmployeeAttendance\n";

// Sample employee attendance data
echo "\n=== SAMPLE EMPLOYEE ATTENDANCE DATA ===\n";
$sampleEmployees = EmployeeAttendance::where('is_active', true)->with('employee')->limit(10)->get();
foreach ($sampleEmployees as $emp) {
    $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
    echo "ID: {$emp->id}, Machine User ID: {$emp->machine_user_id}, Name: {$nama}, Active: " . ($emp->is_active ? 'Yes' : 'No') . "\n";
}

// Check if machine_user_id is empty
echo "\n=== CHECKING EMPTY MACHINE_USER_ID ===\n";
$emptyMachineId = EmployeeAttendance::where('is_active', true)->whereNull('machine_user_id')->count();
echo "Employees with empty machine_user_id: $emptyMachineId\n";

$emptyMachineIdRecords = EmployeeAttendance::where('is_active', true)->whereNull('machine_user_id')->with('employee')->get();
foreach ($emptyMachineIdRecords as $emp) {
    $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
    echo "- ID: {$emp->id}, Name: {$nama}\n";
}

// Check attendance data
echo "\n=== CHECKING ATTENDANCE DATA ===\n";
$totalAttendance = Attendance::count();
echo "Total attendance records: $totalAttendance\n";

$julyAttendance = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->count();
echo "July 2025 attendance records: $julyAttendance\n";

$uniqueUserPins = Attendance::distinct('user_pin')->count();
echo "Unique user_pins in attendance: $uniqueUserPins\n";

$julyUniqueUserPins = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->distinct('user_pin')->count();
echo "Unique user_pins in July 2025: $julyUniqueUserPins\n";

// Sample attendance data
echo "\n=== SAMPLE ATTENDANCE DATA ===\n";
$sampleAttendance = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->limit(5)->get();
foreach ($sampleAttendance as $att) {
    echo "ID: {$att->id}, User PIN: '{$att->user_pin}', Name: {$att->user_name}, Date: {$att->date}, Status: {$att->status}\n";
} 