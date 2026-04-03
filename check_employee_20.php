<?php
use App\Models\Employee;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- CHECKING EMPLOYEE ID 20 ---\n";
$employee = Employee::withTrashed()->find(20);
if ($employee) {
    echo "Employee ID: " . $employee->id . "\n";
    echo "Nama: " . $employee->nama_lengkap . "\n";
    echo "Deleted At: " . ($employee->deleted_at ? $employee->deleted_at->toIso8601String() : 'NULL') . "\n";
    
    $user = User::withTrashed()->where('employee_id', 20)->first();
    if ($user) {
        echo "User ID: " . $user->id . "\n";
        echo "User Name: " . $user->name . "\n";
        echo "User Email: " . $user->email . "\n";
        echo "User Deleted At: " . ($user->deleted_at ? $user->deleted_at->toIso8601String() : 'NULL') . "\n";
    } else {
        echo "No USER found for this employee ID.\n";
    }
} else {
    echo "Employee ID 20 NOT FOUND even in trashed.\n";
}
