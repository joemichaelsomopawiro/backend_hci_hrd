<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\CustomRole;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔄 Memulai update data user untuk custom roles...\n\n";

try {
    DB::beginTransaction();

    // 1. Update department untuk user dengan role kustom
    echo "📝 Update department untuk user dengan role kustom...\n";
    
    $customRoleMappings = [
        'backend' => 'production',
        'frontend' => 'production', 
        'designer' => 'production',
        'developer' => 'production'
    ];

    foreach ($customRoleMappings as $role => $department) {
        $users = User::where('role', $role)->get();
        
        foreach ($users as $user) {
            if ($user->employee) {
                $user->employee->update(['department' => $department]);
                echo "✅ Updated user '{$user->name}' (role: {$role}) -> department: {$department}\n";
            }
        }
    }

    // 2. Update access_level untuk user dengan role kustom
    echo "\n📝 Update access_level untuk user dengan role kustom...\n";
    
    $customRoles = CustomRole::where('is_active', true)->get();
    
    foreach ($customRoles as $customRole) {
        $users = User::where('role', $customRole->role_name)->get();
        
        foreach ($users as $user) {
            $user->update(['access_level' => $customRole->access_level]);
            echo "✅ Updated user '{$user->name}' (role: {$customRole->role_name}) -> access_level: {$customRole->access_level}\n";
        }
    }

    // 3. Update access_level untuk role standar
    echo "\n📝 Update access_level untuk role standar...\n";
    
    $standardRoleMappings = [
        'HR' => 'hr_full',
        'Program Manager' => 'manager',
        'Distribution Manager' => 'manager',
        'VP President' => 'director',
        'President Director' => 'director',
        'Finance' => 'employee',
        'General Affairs' => 'employee',
        'Office Assistant' => 'employee',
        'Producer' => 'employee',
        'Creative' => 'employee',
        'Production' => 'employee',
        'Editor' => 'employee',
        'Social Media' => 'employee',
        'Promotion' => 'employee',
        'Graphic Design' => 'employee',
        'Hopeline Care' => 'employee'
    ];

    foreach ($standardRoleMappings as $role => $accessLevel) {
        $users = User::where('role', $role)->get();
        
        foreach ($users as $user) {
            $user->update(['access_level' => $accessLevel]);
            echo "✅ Updated user '{$user->name}' (role: {$role}) -> access_level: {$accessLevel}\n";
        }
    }

    // 4. Update department untuk role standar
    echo "\n📝 Update department untuk role standar...\n";
    
    $standardDepartmentMappings = [
        'HR' => 'hr',
        'Finance' => 'hr',
        'General Affairs' => 'hr',
        'Office Assistant' => 'hr',
        'Program Manager' => 'production',
        'Producer' => 'production',
        'Creative' => 'production',
        'Production' => 'production',
        'Editor' => 'production',
        'Distribution Manager' => 'distribution',
        'Social Media' => 'distribution',
        'Promotion' => 'distribution',
        'Graphic Design' => 'distribution',
        'Hopeline Care' => 'distribution',
        'VP President' => 'executive',
        'President Director' => 'executive'
    ];

    foreach ($standardDepartmentMappings as $role => $department) {
        $users = User::where('role', $role)->get();
        
        foreach ($users as $user) {
            if ($user->employee) {
                $user->employee->update(['department' => $department]);
                echo "✅ Updated user '{$user->name}' (role: {$role}) -> department: {$department}\n";
            }
        }
    }

    DB::commit();
    
    echo "\n🎉 Update data user berhasil selesai!\n";
    echo "\n📊 Ringkasan perubahan:\n";
    echo "- Department telah diupdate untuk semua user\n";
    echo "- Access level telah diupdate untuk semua user\n";
    echo "- Role kustom sekarang memiliki department dan access level yang sesuai\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Script selesai dijalankan.\n"; 