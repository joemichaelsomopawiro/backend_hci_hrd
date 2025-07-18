<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CustomRole;
use App\Models\User;
use App\Models\Employee;
use App\Services\RoleHierarchyService;

echo "🔍 Mengecek data custom roles dan user backend...\n\n";

// 1. Cek custom roles
echo "📋 Custom Roles:\n";
$customRoles = CustomRole::all();
foreach ($customRoles as $role) {
    echo "- {$role->role_name} (Department: {$role->department}, Access Level: {$role->access_level})\n";
}

echo "\n";

// 2. Cek user dengan role backend
echo "👤 User dengan role 'backend':\n";
$backendUsers = User::where('role', 'backend')->get();
foreach ($backendUsers as $user) {
    echo "- {$user->name} (Role: {$user->role}, Access Level: {$user->access_level})\n";
    if ($user->employee) {
        echo "  Employee Department: {$user->employee->department}\n";
    }
}

echo "\n";

// 3. Cek Program Manager
echo "👨‍💼 Program Manager:\n";
$programManagers = User::where('role', 'Program Manager')->get();
foreach ($programManagers as $user) {
    echo "- {$user->name} (Role: {$user->role}, Access Level: {$user->access_level})\n";
}

echo "\n";

// 4. Test RoleHierarchyService
echo "🔧 Test RoleHierarchyService:\n";
echo "Subordinate roles untuk Program Manager:\n";
$subordinates = RoleHierarchyService::getSubordinateRoles('Program Manager');
foreach ($subordinates as $role) {
    echo "- {$role}\n";
}

echo "\n";

// 5. Cek apakah backend termasuk subordinate Program Manager
echo "✅ Apakah 'backend' termasuk subordinate Program Manager?\n";
if (in_array('backend', $subordinates)) {
    echo "YES! Role 'backend' adalah subordinate dari Program Manager\n";
} else {
    echo "NO! Role 'backend' TIDAK terdeteksi sebagai subordinate dari Program Manager\n";
}

echo "\n";

// 6. Cek leave requests dari user backend
echo "📝 Leave Requests dari user backend:\n";
$backendUser = User::where('role', 'backend')->first();
if ($backendUser && $backendUser->employee) {
    $leaveRequests = \App\Models\LeaveRequest::where('employee_id', $backendUser->employee->id)->get();
    foreach ($leaveRequests as $request) {
        echo "- ID: {$request->id}, Status: {$request->overall_status}, Type: {$request->leave_type}, Dates: {$request->start_date} - {$request->end_date}\n";
    }
} else {
    echo "User backend tidak ditemukan atau tidak memiliki employee data\n";
}

echo "\n✅ Script selesai.\n"; 