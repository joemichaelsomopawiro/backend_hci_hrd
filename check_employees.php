<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING EMPLOYEES ===\n\n";

try {
    $employees = \App\Models\Employee::select('id', 'nama_lengkap', 'nik')->get();
    
    if ($employees->count() > 0) {
        echo "Found " . $employees->count() . " employees:\n";
        foreach ($employees as $employee) {
            echo "ID: {$employee->id} | Name: {$employee->nama_lengkap} | NIK: {$employee->nik}\n";
        }
    } else {
        echo "No employees found in database.\n";
    }
    
    echo "\n=== CHECKING EMPLOYEE ATTENDANCE ===\n";
    $employeeAttendance = \App\Models\EmployeeAttendance::select('employee_id', 'machine_user_id', 'name', 'is_active')->get();
    
    if ($employeeAttendance->count() > 0) {
        echo "Found " . $employeeAttendance->count() . " employee attendance records:\n";
        foreach ($employeeAttendance as $ea) {
            echo "Employee ID: {$ea->employee_id} | Machine User ID: {$ea->machine_user_id} | Name: {$ea->name} | Active: " . ($ea->is_active ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "No employee attendance records found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 