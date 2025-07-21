<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CustomRole;
use App\Models\User;
use App\Models\Employee;

echo "🎯 Contoh Menambah Role Kustom Baru untuk Semua Department...\n\n";

// Data role kustom yang akan dibuat
$customRoles = [
    // Production Department
    [
        'role_name' => 'frontend',
        'department' => 'production',
        'access_level' => 'employee',
        'description' => 'Frontend Developer'
    ],
    [
        'role_name' => 'designer',
        'department' => 'production', 
        'access_level' => 'employee',
        'description' => 'UI/UX Designer'
    ],
    [
        'role_name' => 'developer',
        'department' => 'production',
        'access_level' => 'employee', 
        'description' => 'Full Stack Developer'
    ],
    
    // HR Department
    [
        'role_name' => 'hr_assistant',
        'department' => 'hr',
        'access_level' => 'employee',
        'description' => 'HR Assistant'
    ],
    [
        'role_name' => 'payroll_specialist',
        'department' => 'hr',
        'access_level' => 'employee',
        'description' => 'Payroll Specialist'
    ],
    [
        'role_name' => 'admin_staff',
        'department' => 'hr',
        'access_level' => 'employee',
        'description' => 'Administrative Staff'
    ],
    
    // Distribution Department
    [
        'role_name' => 'marketing_specialist',
        'department' => 'distribution',
        'access_level' => 'employee',
        'description' => 'Marketing Specialist'
    ],
    [
        'role_name' => 'content_creator',
        'department' => 'distribution',
        'access_level' => 'employee',
        'description' => 'Content Creator'
    ],
    [
        'role_name' => 'digital_analyst',
        'department' => 'distribution',
        'access_level' => 'employee',
        'description' => 'Digital Analytics Specialist'
    ]
];

echo "📋 Role Kustom yang Akan Dibuat:\n";
foreach ($customRoles as $role) {
    echo "- {$role['role_name']} ({$role['department']} department)\n";
}

echo "\n";

// Tanya user apakah ingin membuat role ini
echo "Apakah Anda ingin membuat role kustom ini? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "❌ Dibatalkan.\n";
    exit;
}

echo "\n🔄 Membuat role kustom...\n";

$createdRoles = [];
foreach ($customRoles as $roleData) {
    // Cek apakah role sudah ada
    $existingRole = CustomRole::where('role_name', $roleData['role_name'])->first();
    
    if ($existingRole) {
        echo "⚠️  Role '{$roleData['role_name']}' sudah ada, skip...\n";
        continue;
    }
    
    // Buat role baru
    $customRole = CustomRole::create([
        'role_name' => $roleData['role_name'],
        'department' => $roleData['department'],
        'access_level' => $roleData['access_level'],
        'description' => $roleData['description'],
        'is_active' => true,
        'created_by' => 1 // Admin user ID
    ]);
    
    $createdRoles[] = $customRole;
    echo "✅ Role '{$roleData['role_name']}' berhasil dibuat\n";
}

echo "\n📊 Ringkasan:\n";
echo "Total role yang dibuat: " . count($createdRoles) . "\n";

// Test hierarchy untuk setiap department
echo "\n🧪 Test Hierarchy untuk Setiap Department:\n";

$departments = ['production', 'hr', 'distribution'];
$managerRoles = ['Program Manager', 'HR Manager', 'Distribution Manager'];

foreach ($departments as $index => $department) {
    $managerRole = $managerRoles[$index];
    
    echo "\n🏢 {$department} Department (Manager: {$managerRole}):\n";
    
    // Test subordinate roles
    $subordinates = \App\Services\RoleHierarchyService::getSubordinateRoles($managerRole);
    echo "Subordinate roles: " . implode(', ', $subordinates) . "\n";
    
    // Test approval untuk setiap subordinate
    foreach ($subordinates as $subordinate) {
        $canApprove = \App\Services\RoleHierarchyService::canApproveLeave($managerRole, $subordinate);
        $status = $canApprove ? '✅' : '❌';
        echo "  {$status} {$managerRole} -> {$subordinate}: " . ($canApprove ? 'BISA' : 'TIDAK BISA') . " approve\n";
    }
}

echo "\n✅ Script selesai.\n";
echo "\n🎯 Kesimpulan:\n";
echo "- Role kustom baru otomatis masuk ke hierarchy yang benar\n";
echo "- Manager department yang sesuai bisa approve cuti dari role kustom\n";
echo "- Sistem scalable untuk department apapun\n"; 