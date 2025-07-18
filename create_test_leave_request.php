<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\LeaveRequest;
use Carbon\Carbon;

echo "📝 Membuat leave request baru untuk testing...\n\n";

// 1. Cari user backend
$backendUser = User::where('role', 'backend')->first();
if (!$backendUser || !$backendUser->employee) {
    echo "❌ User backend atau employee data tidak ditemukan\n";
    exit;
}

echo "👤 User Backend: {$backendUser->name}\n";

// 2. Buat leave request baru
$startDate = Carbon::tomorrow();
$endDate = Carbon::tomorrow()->addDays(2);

// Hitung hari kerja (tidak termasuk weekend)
$totalDays = 0;
$currentDate = $startDate->copy();
while ($currentDate->lte($endDate)) {
    if (!$currentDate->isWeekend()) {
        $totalDays++;
    }
    $currentDate->addDay();
}

$leaveRequest = LeaveRequest::create([
    'employee_id' => $backendUser->employee->id,
    'leave_type' => 'sick',
    'start_date' => $startDate->toDateString(),
    'end_date' => $endDate->toDateString(),
    'total_days' => $totalDays,
    'reason' => 'Test leave request untuk approval testing',
    'overall_status' => 'pending',
]);

echo "✅ Leave request berhasil dibuat:\n";
echo "- ID: {$leaveRequest->id}\n";
echo "- Type: {$leaveRequest->leave_type}\n";
echo "- Dates: {$leaveRequest->start_date} - {$leaveRequest->end_date}\n";
echo "- Total Days: {$leaveRequest->total_days}\n";
echo "- Status: {$leaveRequest->overall_status}\n";

echo "\n✅ Script selesai.\n"; 