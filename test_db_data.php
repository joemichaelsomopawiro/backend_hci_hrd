<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use App\Models\Employee;

echo "=== Database Test ===\n";
echo "Employees: " . Employee::count() . "\n";
echo "Attendances: " . Attendance::count() . "\n";
echo "Attendances with employee_id: " . Attendance::whereNotNull('employee_id')->count() . "\n";

// Get all employees
echo "\nAll Employees:\n";
$employees = Employee::all();
foreach ($employees as $employee) {
    echo "ID: " . $employee->id . " - Name: " . $employee->nama_lengkap . "\n";
}

// Get sample data
$attendance = Attendance::whereNotNull('employee_id')->first();
if ($attendance) {
    echo "\nSample attendance employee_id: " . $attendance->employee_id . "\n";
    echo "Sample attendance date: " . $attendance->date . "\n";
    echo "Sample attendance status: " . $attendance->status . "\n";
} else {
    echo "No attendance with employee_id found\n";
}

echo "\n=== Test Complete ===\n"; 