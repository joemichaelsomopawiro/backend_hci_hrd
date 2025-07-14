<?php
// fix_hr_roles.php
// Script untuk memperbaiki role user HR yang salah

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fixing HR Roles in Database ===\n\n";

try {
    // Cek user dengan role 'hr' (huruf kecil)
    $lowercaseHR = DB::table('users')
        ->where('role', 'hr')
        ->get();

    if ($lowercaseHR->count() > 0) {
        echo "Found " . $lowercaseHR->count() . " users with role 'hr' (lowercase)\n";
        echo "Updating to 'HR' (uppercase)...\n";
        
        DB::table('users')
            ->where('role', 'hr')
            ->update(['role' => 'HR']);
        
        echo "✅ Updated " . $lowercaseHR->count() . " users from 'hr' to 'HR'\n";
    } else {
        echo "No users with role 'hr' (lowercase) found.\n";
    }

    // Cek user dengan role 'hr_manager'
    $hrManager = DB::table('users')
        ->where('role', 'hr_manager')
        ->get();

    if ($hrManager->count() > 0) {
        echo "\nFound " . $hrManager->count() . " users with role 'hr_manager'\n";
        echo "Updating to 'HR'...\n";
        
        DB::table('users')
            ->where('role', 'hr_manager')
            ->update(['role' => 'HR']);
        
        echo "✅ Updated " . $hrManager->count() . " users from 'hr_manager' to 'HR'\n";
    } else {
        echo "No users with role 'hr_manager' found.\n";
    }

    // Verifikasi hasil
    echo "\n=== Verification ===\n";
    $hrUsers = DB::table('users')
        ->where('role', 'HR')
        ->select('id', 'name', 'email', 'role')
        ->get();

    echo "Total HR users after fix: " . $hrUsers->count() . "\n";
    
    if ($hrUsers->count() > 0) {
        echo "HR users:\n";
        foreach ($hrUsers as $user) {
            echo "- " . $user->name . " (" . $user->email . ")\n";
        }
    }

    // Cek apakah masih ada role yang salah
    $wrongRoles = DB::table('users')
        ->whereIn('role', ['hr', 'hr_manager'])
        ->count();

    if ($wrongRoles > 0) {
        echo "\n⚠️  WARNING: Still found " . $wrongRoles . " users with wrong roles!\n";
    } else {
        echo "\n✅ All HR roles are now correct!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
echo "You can now test the calendar API with HR users.\n";
?> 