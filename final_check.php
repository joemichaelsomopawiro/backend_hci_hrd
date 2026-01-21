<?php

use App\Models\User;
use App\Models\Employee;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = User::with('employee')->where('email', 'like', '%@joe.com')->get();

$mask = "| %-30.30s | %-20.20s | %-20.20s | %-10.10s |\n";
$output = sprintf($mask, 'EMAIL', 'USER ROLE', 'JABATAN', 'PASSWORD');
$output .= sprintf($mask, str_repeat('-', 30), str_repeat('-', 20), str_repeat('-', 20), str_repeat('-', 10));

foreach ($users as $user) {
    $jabatan = $user->employee ? $user->employee->jabatan_saat_ini : 'N/A';
    $output .= sprintf($mask, $user->email, $user->role, $jabatan, 'password');
}

file_put_contents('final_credentials.txt', $output);
echo "Written to final_credentials.txt\n";
