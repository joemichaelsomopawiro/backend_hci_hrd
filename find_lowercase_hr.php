<?php
// find_lowercase_hr.php
// Script untuk menemukan user dengan role 'hr' (lowercase)

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Finding User with Role 'hr' (lowercase) ===\n\n";

try {
    // Cari user dengan role 'hr' (lowercase)
    $lowercaseHR = DB::select("SELECT id, name, email, role, employee_id FROM users WHERE role = 'hr'");
    
    if (count($lowercaseHR) > 0) {
        echo "Found " . count($lowercaseHR) . " user(s) with role 'hr' (lowercase):\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($lowercaseHR as $user) {
            echo "ID: " . $user->id . "\n";
            echo "Name: " . $user->name . "\n";
            echo "Email: " . $user->email . "\n";
            echo "Role: '" . $user->role . "'\n";
            echo "Employee ID: " . ($user->employee_id ?? 'NULL') . "\n";
            echo str_repeat("-", 40) . "\n";
        }
        
        // Update role ke 'HR' (uppercase)
        echo "\nUpdating role from 'hr' to 'HR'...\n";
        DB::update("UPDATE users SET role = 'HR' WHERE role = 'hr'");
        echo "✅ Updated " . count($lowercaseHR) . " user(s)\n";
        
    } else {
        echo "No users found with role 'hr' (lowercase).\n";
    }
    
    // Verifikasi setelah update
    echo "\n=== Verification ===\n";
    $hrUsers = DB::select("SELECT id, name, email, role FROM users WHERE role = 'HR'");
    echo "Total users with role 'HR' (uppercase): " . count($hrUsers) . "\n";
    
    if (count($hrUsers) > 0) {
        echo "HR users:\n";
        foreach ($hrUsers as $user) {
            echo "- " . $user->name . " (" . $user->email . ")\n";
        }
    }
    
    // Cek apakah masih ada role 'hr' (lowercase)
    $remainingLowercase = DB::select("SELECT COUNT(*) as count FROM users WHERE role = 'hr'");
    if ($remainingLowercase[0]->count > 0) {
        echo "\n⚠️  WARNING: Still found " . $remainingLowercase[0]->count . " users with role 'hr' (lowercase)!\n";
    } else {
        echo "\n✅ All HR roles are now 'HR' (uppercase)!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
?> 