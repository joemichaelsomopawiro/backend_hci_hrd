<?php
// test_calendar_post.php
// Script untuk test POST /api/calendar endpoint

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test POST /api/calendar Endpoint ===\n\n";

try {
    // Cek user HR untuk mendapatkan token
    $hrUser = DB::table('users')
        ->where('role', 'HR')
        ->first();
    
    if (!$hrUser) {
        echo "❌ No HR user found!\n";
        exit;
    }
    
    echo "HR User found: " . $hrUser->name . " (" . $hrUser->email . ")\n";
    
    // Buat token untuk user HR
    $token = $hrUser->id . '|' . md5($hrUser->email . time());
    
    echo "Generated token: " . substr($token, 0, 20) . "...\n\n";
    
    // Test data untuk hari libur
    $holidayData = [
        'date' => '2024-12-26',
        'name' => 'Libur Test API',
        'description' => 'Libur untuk testing API endpoint',
        'type' => 'custom'
    ];
    
    echo "Test data:\n";
    echo json_encode($holidayData, JSON_PRETTY_PRINT) . "\n\n";
    
    // Simulasi request ke endpoint
    echo "=== Simulating POST Request ===\n";
    
    // Buat request instance
    $request = new \Illuminate\Http\Request();
    $request->merge($holidayData);
    
    // Set user untuk request
    $request->setUserResolver(function () use ($hrUser) {
        return (object) [
            'id' => $hrUser->id,
            'role' => $hrUser->role,
            'name' => $hrUser->name,
            'email' => $hrUser->email
        ];
    });
    
    // Buat controller instance
    $controller = new \App\Http\Controllers\NationalHolidayController();
    
    // Test method store
    echo "Calling NationalHolidayController@store...\n";
    
    try {
        $response = $controller->store($request);
        echo "✅ Response received!\n";
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Content: " . $response->getContent() . "\n";
    } catch (Exception $e) {
        echo "❌ Error calling controller: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?> 