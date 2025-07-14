<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Cari user HR
$hrUser = User::where('role', 'HR')->first();

if (!$hrUser) {
    echo "❌ Tidak ada user dengan role HR\n";
    exit;
}

echo "✅ Menggunakan user HR: {$hrUser->name} (ID: {$hrUser->id}, Role: {$hrUser->role})\n";

// Login user dan dapatkan token
$credentials = [
    'email' => $hrUser->email,
    'password' => 'password' // Assuming default password
];

// Test login
$response = $app->handle(
    \Illuminate\Http\Request::create('/api/login', 'POST', $credentials)
);

$loginData = json_decode($response->getContent(), true);

echo "Login Response Status: " . $response->getStatusCode() . "\n";
echo "Login Response: " . json_encode($loginData, JSON_PRETTY_PRINT) . "\n";

if (!$loginData || !isset($loginData['success']) || !$loginData['success']) {
    echo "❌ Login gagal\n";
    exit;
}

$token = $loginData['data']['token'];
echo "✅ Login berhasil, token: " . substr($token, 0, 20) . "...\n";

// Test tambah hari libur
$testData = [
    'date' => '2025-01-15',
    'name' => 'Test Holiday via API',
    'description' => 'Test description via API',
    'type' => 'custom'
];

echo "\n=== Testing API Calendar Endpoint ===\n";
echo "Data yang akan dikirim: " . json_encode($testData) . "\n";

$request = \Illuminate\Http\Request::create('/api/calendar', 'POST', $testData);
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Content-Type', 'application/json');

$response = $app->handle($request);
$responseData = json_decode($response->getContent(), true);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";

if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
    echo "✅ API berhasil menambah hari libur\n";
    
    // Hapus data test
    if (isset($responseData['data']['id'])) {
        $deleteRequest = \Illuminate\Http\Request::create('/api/calendar/' . $responseData['data']['id'], 'DELETE');
        $deleteRequest->headers->set('Authorization', 'Bearer ' . $token);
        $deleteRequest->headers->set('Accept', 'application/json');
        
        $deleteResponse = $app->handle($deleteRequest);
        echo "🗑️  Data test berhasil dihapus\n";
    }
} else {
    echo "❌ API gagal menambah hari libur\n";
}

echo "\n=== Selesai Testing ===\n"; 