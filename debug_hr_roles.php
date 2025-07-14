<?php
// debug_hr_roles.php
// Script untuk debug user dengan role yang bermasalah

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug HR Roles ===\n\n";

try {
    // Cek semua user dengan role yang mengandung 'hr'
    $hrUsers = DB::table('users')
        ->where('role', 'like', '%hr%')
        ->select('id', 'name', 'email', 'role', 'employee_id')
        ->get();

    echo "All users with 'hr' in role:\n";
    echo str_repeat("-", 60) . "\n";

    foreach ($hrUsers as $user) {
        echo "ID: " . $user->id . "\n";
        echo "Name: " . $user->name . "\n";
        echo "Email: " . $user->email . "\n";
        echo "Role: '" . $user->role . "' (length: " . strlen($user->role) . ")\n";
        echo "Employee ID: " . ($user->employee_id ?? 'Not linked') . "\n";
        echo str_repeat("-", 40) . "\n";
    }

    // Cek user dengan role 'hr' (lowercase) secara spesifik
    $lowercaseHR = DB::table('users')
        ->where('role', 'hr')
        ->select('id', 'name', 'email', 'role')
        ->get();

    echo "\nUsers with role 'hr' (lowercase):\n";
    if ($lowercaseHR->count() > 0) {
        foreach ($lowercaseHR as $user) {
            echo "- " . $user->name . " (" . $user->email . ") - Role: '" . $user->role . "'\n";
        }
    } else {
        echo "None found.\n";
    }

    // Cek user dengan role 'HR' (uppercase) secara spesifik
    $uppercaseHR = DB::table('users')
        ->where('role', 'HR')
        ->select('id', 'name', 'email', 'role')
        ->get();

    echo "\nUsers with role 'HR' (uppercase):\n";
    if ($uppercaseHR->count() > 0) {
        foreach ($uppercaseHR as $user) {
            echo "- " . $user->name . " (" . $user->email . ") - Role: '" . $user->role . "'\n";
        }
    } else {
        echo "None found.\n";
    }

    // Cek semua role yang ada
    echo "\nAll unique roles in database:\n";
    $allRoles = DB::table('users')
        ->select('role')
        ->distinct()
        ->orderBy('role')
        ->get();

    foreach ($allRoles as $role) {
        echo "- '" . $role->role . "' (length: " . strlen($role->role) . ")\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?> 