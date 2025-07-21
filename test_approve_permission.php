<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\LeaveRequest;
use App\Services\RoleHierarchyService;

echo "🧪 Test Program Manager Approval Permission untuk Backend...\n\n";

// 1. Cari Program Manager
$programManager = User::where('role', 'Program Manager')->first();
if (!$programManager) {
    echo "❌ Program Manager tidak ditemukan\n";
    exit;
}

echo "👨‍💼 Program Manager: {$programManager->name}\n";

// 2. Cari user backend
$backendUser = User::where('role', 'backend')->first();
if (!$backendUser) {
    echo "❌ User backend tidak ditemukan\n";
    exit;
}

echo "👤 User Backend: {$backendUser->name}\n";

// 3. Cari leave request dari backend
$leaveRequest = null;
if ($backendUser->employee) {
    $leaveRequest = LeaveRequest::where('employee_id', $backendUser->employee->id)
        ->where('overall_status', 'pending')
        ->first();
}

if (!$leaveRequest) {
    echo "❌ Leave request pending dari backend tidak ditemukan\n";
    exit;
}

echo "📝 Leave Request: ID {$leaveRequest->id}, Type: {$leaveRequest->leave_type}\n";

// 4. Test canApproveLeave
echo "\n🔧 Test canApproveLeave:\n";
$canApprove = RoleHierarchyService::canApproveLeave('Program Manager', 'backend');
echo "canApproveLeave('Program Manager', 'backend') = " . ($canApprove ? 'TRUE' : 'FALSE') . "\n";

if ($canApprove) {
    echo "✅ Program Manager BISA approve leave request dari backend\n";
} else {
    echo "❌ Program Manager TIDAK BISA approve leave request dari backend\n";
}

// 5. Test department mapping
echo "\n🏢 Test Department Mapping:\n";
$programManagerDept = RoleHierarchyService::getDepartmentForRole('Program Manager');
$backendDept = RoleHierarchyService::getDepartmentForRole('backend');
echo "Program Manager Department: {$programManagerDept}\n";
echo "Backend Department: {$backendDept}\n";

if ($programManagerDept === $backendDept) {
    echo "✅ Department sama: {$programManagerDept}\n";
} else {
    echo "❌ Department berbeda\n";
}

// 6. Test custom role data
echo "\n🔍 Test Custom Role Data:\n";
$customRole = \App\Models\CustomRole::where('role_name', 'backend')->first();
if ($customRole) {
    echo "Custom Role 'backend':\n";
    echo "- Department: {$customRole->department}\n";
    echo "- Access Level: {$customRole->access_level}\n";
    echo "- Is Active: " . ($customRole->is_active ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ Custom role 'backend' tidak ditemukan\n";
}

// 7. Test isManager
echo "\n👑 Test isManager:\n";
$isManager = RoleHierarchyService::isManager('Program Manager');
echo "isManager('Program Manager') = " . ($isManager ? 'TRUE' : 'FALSE') . "\n";

// 8. Test isCustomRole
echo "\n🎭 Test isCustomRole:\n";
$isCustomRole = RoleHierarchyService::isCustomRole('backend');
echo "isCustomRole('backend') = " . ($isCustomRole ? 'TRUE' : 'FALSE') . "\n";

echo "\n✅ Test selesai.\n"; 