<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\LeaveRequest;
use App\Services\RoleHierarchyService;

echo "🧪 Test Program Manager Access untuk Leave Request Backend...\n\n";

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

// 3. Cek subordinate roles untuk Program Manager
echo "\n🔧 Subordinate roles untuk Program Manager:\n";
$subordinateRoles = RoleHierarchyService::getSubordinateRoles('Program Manager');
foreach ($subordinateRoles as $role) {
    echo "- {$role}\n";
}

// 4. Cek apakah backend termasuk subordinate
if (in_array('backend', $subordinateRoles)) {
    echo "\n✅ Role 'backend' adalah subordinate dari Program Manager\n";
} else {
    echo "\n❌ Role 'backend' TIDAK terdeteksi sebagai subordinate dari Program Manager\n";
}

// 5. Cek leave requests dari backend
echo "\n📝 Leave Requests dari backend:\n";
if ($backendUser->employee) {
    $leaveRequests = LeaveRequest::where('employee_id', $backendUser->employee->id)->get();
    foreach ($leaveRequests as $request) {
        echo "- ID: {$request->id}, Status: {$request->overall_status}, Type: {$request->leave_type}\n";
    }
} else {
    echo "User backend tidak memiliki employee data\n";
}

// 6. Simulasi query yang dilakukan LeaveRequestController
echo "\n🔍 Simulasi query LeaveRequestController untuk Program Manager:\n";

$query = LeaveRequest::with(['employee.user', 'approvedBy.user']);

// Filter berdasarkan subordinate roles
if (!empty($subordinateRoles)) {
    $query->whereHas('employee.user', function ($q) use ($subordinateRoles) {
        $q->whereIn('role', $subordinateRoles);
    });
}

$requests = $query->orderBy('created_at', 'desc')->get();

echo "Total leave requests yang bisa dilihat Program Manager: " . $requests->count() . "\n";

foreach ($requests as $request) {
    $employeeName = $request->employee->nama_lengkap ?? 'Unknown';
    $employeeRole = $request->employee->user->role ?? 'Unknown';
    echo "- {$employeeName} ({$employeeRole}): {$request->leave_type} - {$request->overall_status}\n";
}

echo "\n✅ Test selesai.\n"; 