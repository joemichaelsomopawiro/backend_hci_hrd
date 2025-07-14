<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Test Endpoint /api/calendar ===\n";

// Cari user HR untuk login
$hrUser = User::where('role', 'HR')->first();

if (!$hrUser) {
    echo "❌ User HR tidak ditemukan\n";
    exit;
}

echo "✅ Menggunakan user HR: {$hrUser->name} (ID: {$hrUser->id})\n";

// Login untuk dapat token
$loginRequest = \Illuminate\Http\Request::create('/api/auth/login', 'POST', [
    'login' => $hrUser->email,
    'password' => 'password'
]);

$loginResponse = $app->handle($loginRequest);
$loginData = json_decode($loginResponse->getContent(), true);

if (!isset($loginData['data']['token'])) {
    echo "❌ Login gagal: " . json_encode($loginData) . "\n";
    exit;
}

$token = $loginData['data']['token'];
echo "✅ Login berhasil, token: " . substr($token, 0, 20) . "...\n";

// Test endpoint /api/calendar
$calendarRequest = \Illuminate\Http\Request::create('/api/calendar', 'GET', [
    'year' => '2025',
    'month' => '7'
]);

$calendarRequest->headers->set('Authorization', 'Bearer ' . $token);
$calendarRequest->headers->set('Accept', 'application/json');

$calendarResponse = $app->handle($calendarRequest);
$calendarData = json_decode($calendarResponse->getContent(), true);

echo "\n=== Response /api/calendar ===\n";
echo "Status Code: " . $calendarResponse->getStatusCode() . "\n";

if ($calendarResponse->getStatusCode() === 200) {
    echo "✅ API berhasil\n";
    
    if (isset($calendarData['success']) && $calendarData['success']) {
        echo "✅ Response success: true\n";
        
        if (isset($calendarData['data']) && is_array($calendarData['data'])) {
            echo "📊 Jumlah data: " . count($calendarData['data']) . "\n";
            
            foreach ($calendarData['data'] as $holiday) {
                echo "   {$holiday['date']} - {$holiday['name']} ({$holiday['type']})\n";
            }
        } else {
            echo "❌ Data tidak ada atau bukan array\n";
        }
    } else {
        echo "❌ Response success: false\n";
    }
} else {
    echo "❌ API gagal\n";
}

echo "\n=== Response Detail ===\n";
echo json_encode($calendarData, JSON_PRETTY_PRINT) . "\n"; 