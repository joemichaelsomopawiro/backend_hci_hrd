<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Http;

echo "🌐 Test API Endpoint Reject untuk Program Manager...\n\n";

// 1. Cari Program Manager dan generate token
$programManager = User::where('role', 'Program Manager')->first();
if (!$programManager) {
    echo "❌ Program Manager tidak ditemukan\n";
    exit;
}

echo "👨‍💼 Program Manager: {$programManager->name}\n";

// 2. Generate token untuk Program Manager
$token = $programManager->createToken('test-token')->plainTextToken;
echo "🔑 Token generated: " . substr($token, 0, 20) . "...\n\n";

// 3. Cari leave request pending dari backend
$backendUser = User::where('role', 'backend')->first();
if (!$backendUser || !$backendUser->employee) {
    echo "❌ User backend atau employee data tidak ditemukan\n";
    exit;
}

$leaveRequest = LeaveRequest::where('employee_id', $backendUser->employee->id)
    ->where('overall_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$leaveRequest) {
    echo "❌ Leave request pending dari backend tidak ditemukan\n";
    exit;
}

echo "📝 Leave Request: ID {$leaveRequest->id}, Type: {$leaveRequest->leave_type}\n\n";

// 4. Test API endpoint reject
echo "📡 Testing PUT /api/leave-requests/{$leaveRequest->id}/reject...\n";

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json'
])->put("http://localhost:8000/api/leave-requests/{$leaveRequest->id}/reject", [
    'rejection_reason' => 'Test rejection dari Program Manager - alasan: jadwal kerja sedang padat'
]);

echo "Status Code: " . $response->status() . "\n";
echo "Response:\n";

$data = $response->json();
if (isset($data['success']) && $data['success']) {
    echo "✅ Success: " . $data['message'] . "\n";
    echo "Leave Request Status: " . $data['data']['overall_status'] . "\n";
    echo "Rejection Reason: " . $data['data']['rejection_reason'] . "\n";
} else {
    echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    echo "Full Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

echo "\n✅ Test selesai.\n"; 