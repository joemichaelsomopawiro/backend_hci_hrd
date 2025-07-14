<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test API endpoint
echo "=== Test API Calendar Endpoint ===\n";

// Test GET /api/calendar (index method)
$request = \Illuminate\Http\Request::create('/api/calendar', 'GET', [
    'year' => '2025',
    'month' => '7'
]);

$request->headers->set('Accept', 'application/json');

$response = $app->handle($request);
$responseData = json_decode($response->getContent(), true);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";

if ($response->getStatusCode() === 200) {
    echo "✅ API berhasil mengembalikan data\n";
    
    if (isset($responseData['data']) && is_array($responseData['data'])) {
        echo "📊 Jumlah data: " . count($responseData['data']) . "\n";
        
        foreach ($responseData['data'] as $holiday) {
            echo "   {$holiday['date']} - {$holiday['name']} ({$holiday['type']})\n";
        }
    }
} else {
    echo "❌ API gagal mengembalikan data\n";
}

echo "\n=== Test API dengan Authentication ===\n";

// Test dengan authentication
$hrUser = \App\Models\User::where('role', 'HR')->first();

if ($hrUser) {
    // Login untuk dapat token
    $loginRequest = \Illuminate\Http\Request::create('/api/login', 'POST', [
        'email' => $hrUser->email,
        'password' => 'password'
    ]);
    
    $loginResponse = $app->handle($loginRequest);
    $loginData = json_decode($loginResponse->getContent(), true);
    
    if (isset($loginData['data']['token'])) {
        $token = $loginData['data']['token'];
        
        // Test API dengan token
        $authRequest = \Illuminate\Http\Request::create('/api/calendar', 'GET', [
            'year' => '2025',
            'month' => '7'
        ]);
        
        $authRequest->headers->set('Authorization', 'Bearer ' . $token);
        $authRequest->headers->set('Accept', 'application/json');
        
        $authResponse = $app->handle($authRequest);
        $authResponseData = json_decode($authResponse->getContent(), true);
        
        echo "Status Code (Auth): " . $authResponse->getStatusCode() . "\n";
        
        if ($authResponse->getStatusCode() === 200) {
            echo "✅ API dengan auth berhasil\n";
            if (isset($authResponseData['data']) && is_array($authResponseData['data'])) {
                echo "📊 Jumlah data (Auth): " . count($authResponseData['data']) . "\n";
            }
        } else {
            echo "❌ API dengan auth gagal\n";
        }
    } else {
        echo "❌ Login gagal\n";
    }
} else {
    echo "❌ User HR tidak ditemukan\n";
} 