<?php
use App\Models\Employee;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = "--- VERIFICATION FOR EMPLOYEE ID 20 ---\n";
$employee = Employee::withTrashed()->find(20);

if ($employee) {
    $out .= "ID: " . $employee->id . "\n";
    $out .= "Nama: " . $employee->nama_lengkap . "\n";
    $out .= "Is Trashed: " . ($employee->trashed() ? 'YES' : 'NO') . "\n";
    $out .= "Deleted At: " . ($employee->deleted_at ?: 'NULL') . "\n";
    
    $user = User::where('employee_id', 20)->first();
    if ($user) {
        $out .= "User ID: " . $user->id . "\n";
        $out .= "User Name: " . $user->name . "\n";
        $out .= "User Is Active: " . ($user->is_active ? 'YES' : 'NO') . "\n";
    } else {
        $out .= "NO USER FOUND with employee_id = 20\n";
    }
} else {
    $out .= "EMPLOYEE ID 20 NOT FOUND even with trashed.\n";
}

file_put_contents('c:\xampp\htdocs\backend_hci\verification_result.txt', $out);
echo "DONE\n";
