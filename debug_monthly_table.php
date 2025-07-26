<?php
// Debug script untuk monthly table
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EmployeeAttendance;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;

echo "=== DEBUG MONTHLY TABLE ===\n\n";

$month = 7;
$year = 2025;

echo "Target: Month $month, Year $year\n\n";

// 1. Check EmployeeAttendance data
echo "1. CHECKING EMPLOYEE ATTENDANCE DATA:\n";
$employeeAttendances = EmployeeAttendance::where('is_active', true)->with('employee')->get();
echo "Total active employee attendance records: " . $employeeAttendances->count() . "\n";

if ($employeeAttendances->count() > 0) {
    echo "Sample records:\n";
    foreach ($employeeAttendances->take(5) as $emp) {
        $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
        echo "- ID: {$emp->id}, Machine User ID: '{$emp->machine_user_id}', Name: {$nama}\n";
    }
} else {
    echo "❌ No active employee attendance records found!\n";
}

echo "\n";

// 2. Check Attendance data for July 2025
echo "2. CHECKING ATTENDANCE DATA FOR JULY 2025:\n";
$attendances = Attendance::whereYear('date', $year)->whereMonth('date', $month)->get();
echo "Total attendance records for July 2025: " . $attendances->count() . "\n";

if ($attendances->count() > 0) {
    echo "Sample attendance records:\n";
    foreach ($attendances->take(5) as $att) {
        echo "- ID: {$att->id}, User PIN: '{$att->user_pin}', Name: {$att->user_name}, Date: {$att->date}\n";
    }
} else {
    echo "❌ No attendance records found for July 2025!\n";
}

echo "\n";

// 3. Check unique user_pins
echo "3. CHECKING UNIQUE USER PINS:\n";
$uniqueUserPins = Attendance::distinct('user_pin')->pluck('user_pin')->toArray();
echo "Total unique user_pins in attendance: " . count($uniqueUserPins) . "\n";
echo "User PINs: " . implode(', ', array_map(function($pin) { return "'$pin'"; }, $uniqueUserPins)) . "\n";

echo "\n";

// 4. Check machine_user_ids from EmployeeAttendance
echo "4. CHECKING MACHINE USER IDS:\n";
$machineUserIds = EmployeeAttendance::where('is_active', true)->pluck('machine_user_id')->toArray();
echo "Total machine_user_ids: " . count($machineUserIds) . "\n";
echo "Machine User IDs: " . implode(', ', array_map(function($id) { return "'$id'"; }, $machineUserIds)) . "\n";

echo "\n";

// 5. Check intersection
echo "5. CHECKING INTERSECTION:\n";
$intersection = array_intersect($uniqueUserPins, $machineUserIds);
echo "Common PINs between attendance and employee_attendance: " . count($intersection) . "\n";
echo "Common PINs: " . implode(', ', array_map(function($pin) { return "'$pin'"; }, $intersection)) . "\n";

echo "\n";

// 6. Simulate the monthly table logic
echo "6. SIMULATING MONTHLY TABLE LOGIC:\n";

// Get all employees from EmployeeAttendance
$allEmployees = EmployeeAttendance::where('is_active', true)
    ->with('employee')
    ->get()
    ->map(function($empAttendance) {
        return (object) [
            'user_pin' => $empAttendance->machine_user_id,
            'user_name' => $empAttendance->employee ? $empAttendance->employee->nama_lengkap : $empAttendance->name,
            'card_number' => $empAttendance->employee ? $empAttendance->employee->card_number : null
        ];
    })
    ->keyBy('user_pin');

echo "Employees from EmployeeAttendance: " . $allEmployees->count() . "\n";

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

echo "\n✅ Debug completed!\n"; 