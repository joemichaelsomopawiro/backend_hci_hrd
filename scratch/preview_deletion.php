<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$users = DB::table('users')->get();
$employees = DB::table('employees')->get();

echo "--- USERS ---\n";
$usersToDelete = [];
foreach ($users as $user) {
    if (!str_ends_with($user->email, '@joe.com')) {
        echo "DELETE: {$user->id} | {$user->email}\n";
        $usersToDelete[] = $user;
    } else {
        echo "KEEP:   {$user->id} | {$user->email}\n";
    }
}

echo "\n--- EMPLOYEES ---\n";
$employeesToDelete = [];
foreach ($employees as $emp) {
    $email = $emp->email ?? 'N/A';
    if (!str_ends_with($email, '@joe.com')) {
        echo "DELETE: {$emp->id} | " . ($emp->nama_lengkap ?? $emp->name ?? 'Unknown') . " | {$email}\n";
        $employeesToDelete[] = $emp;
    } else {
        echo "KEEP:   {$emp->id} | " . ($emp->nama_lengkap ?? $emp->name ?? 'Unknown') . " | {$email}\n";
    }
}

echo "\nSummary:\n";
echo "Total Users to Delete: " . count($usersToDelete) . "\n";
echo "Total Employees to Delete: " . count($employeesToDelete) . "\n";
