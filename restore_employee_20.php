<?php
use App\Models\Employee;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- RESTORING EMPLOYEE ID 20 ---\n";
$employee = Employee::withTrashed()->find(20);

if ($employee) {
    if ($employee->trashed()) {
        $employee->restore();
        echo "Employee ID 20 RESTORED.\n";
    } else {
        echo "Employee ID 20 was NOT trashed.\n";
    }
    
    $user = User::withTrashed()->where('employee_id', 20)->first();
    if ($user) {
        $user->update(['is_active' => true]);
        echo "User ID {$user->id} reactivated (is_active = true).\n";
    }
} else {
    echo "Employee ID 20 NOT FOUND.\n";
}
