<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

echo "🌐 Test API Endpoint untuk Program Manager...\n\n";

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

// 3. Test API endpoint /api/leave-requests
echo "📡 Testing GET /api/leave-requests...\n";

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json'
])->get('http://localhost:8000/api/leave-requests');

echo "Status Code: " . $response->status() . "\n";
echo "Response:\n";

$data = $response->json();
if (isset($data['success']) && $data['success']) {
    echo "✅ Success: " . count($data['data']) . " leave requests found\n";
    
    foreach ($data['data'] as $request) {
        $employeeName = $request['employee']['nama_lengkap'] ?? 'Unknown';
        $employeeRole = $request['employee']['user']['role'] ?? 'Unknown';
        echo "- {$employeeName} ({$employeeRole}): {$request['leave_type']} - {$request['overall_status']}\n";
    }
} else {
    echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\n";

// 4. Test dengan filter pending
echo "📡 Testing GET /api/leave-requests?status=pending...\n";

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json'
])->get('http://localhost:8000/api/leave-requests?status=pending');

echo "Status Code: " . $response->status() . "\n";
echo "Response:\n";

$data = $response->json();
if (isset($data['success']) && $data['success']) {
    echo "✅ Success: " . count($data['data']) . " pending leave requests found\n";
    
    foreach ($data['data'] as $request) {
        $employeeName = $request['employee']['nama_lengkap'] ?? 'Unknown';
        $employeeRole = $request['employee']['user']['role'] ?? 'Unknown';
        echo "- {$employeeName} ({$employeeRole}): {$request['leave_type']} - {$request['overall_status']}\n";
    }
} else {
    echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\n✅ Test selesai.\n"; 