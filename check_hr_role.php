<?php
// check_hr_role.php
// Script untuk mengecek role user HR di database

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking HR Users in Database ===\n\n";

try {
    // Cek semua user dengan role HR
    $hrUsers = DB::table('users')
        ->where('role', 'HR')
        ->select('id', 'name', 'email', 'role', 'employee_id')
        ->get();

    echo "Found " . $hrUsers->count() . " HR users:\n";
    echo str_repeat("-", 50) . "\n";

    if ($hrUsers->count() > 0) {
        foreach ($hrUsers as $user) {
            echo "ID: " . $user->id . "\n";
            echo "Name: " . $user->name . "\n";
            echo "Email: " . $user->email . "\n";
            echo "Role: " . $user->role . "\n";
            echo "Employee ID: " . ($user->employee_id ?? 'Not linked') . "\n";
            echo str_repeat("-", 30) . "\n";
        }
    } else {
        echo "No HR users found!\n";
    }

    // Cek semua role yang ada di database
    echo "\n=== All Roles in Database ===\n";
    $allRoles = DB::table('users')
        ->select('role')
        ->distinct()
        ->orderBy('role')
        ->pluck('role');

    echo "Available roles:\n";
    foreach ($allRoles as $role) {
        echo "- " . $role . "\n";
    }

    // Cek apakah ada user dengan role 'hr' (huruf kecil)
    $lowercaseHR = DB::table('users')
        ->where('role', 'hr')
        ->count();

    if ($lowercaseHR > 0) {
        echo "\n⚠️  WARNING: Found " . $lowercaseHR . " users with role 'hr' (lowercase)\n";
        echo "This might cause access issues. Consider updating to 'HR' (uppercase)\n";
    }

    // Cek apakah ada user dengan role 'hr_manager'
    $hrManager = DB::table('users')
        ->where('role', 'hr_manager')
        ->count();

    if ($hrManager > 0) {
        echo "\n⚠️  WARNING: Found " . $hrManager . " users with role 'hr_manager'\n";
        echo "This role is not in the current schema. Consider updating to 'HR'\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?> 