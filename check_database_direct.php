<?php
// check_database_direct.php
// Script untuk cek database secara langsung

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Direct Database Check ===\n\n";

try {
    // Cek semua user dengan query SQL langsung
    $users = DB::select("SELECT id, name, email, role, employee_id FROM users ORDER BY id");
    
    echo "All users in database:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($users as $user) {
        echo "ID: " . $user->id . " | ";
        echo "Name: " . $user->name . " | ";
        echo "Email: " . $user->email . " | ";
        echo "Role: '" . $user->role . "' | ";
        echo "Employee ID: " . ($user->employee_id ?? 'NULL') . "\n";
    }
    
    echo "\n=== Specific Role Checks ===\n";
    
    // Cek role 'hr' (lowercase)
    $lowercaseHR = DB::select("SELECT COUNT(*) as count FROM users WHERE role = 'hr'");
    echo "Users with role 'hr' (lowercase): " . $lowercaseHR[0]->count . "\n";
    
    // Cek role 'HR' (uppercase)
    $uppercaseHR = DB::select("SELECT COUNT(*) as count FROM users WHERE role = 'HR'");
    echo "Users with role 'HR' (uppercase): " . $uppercaseHR[0]->count . "\n";
    
    // Cek role 'hr_manager'
    $hrManager = DB::select("SELECT COUNT(*) as count FROM users WHERE role = 'hr_manager'");
    echo "Users with role 'hr_manager': " . $hrManager[0]->count . "\n";
    
    // Cek semua role yang unik
    echo "\n=== All Unique Roles ===\n";
    $uniqueRoles = DB::select("SELECT DISTINCT role FROM users ORDER BY role");
    foreach ($uniqueRoles as $role) {
        echo "- '" . $role->role . "'\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?> 