<?php
// test_calendar_with_auth.php
// Script untuk test calendar dengan autentikasi yang benar

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test Calendar with Authentication ===\n\n";

try {
    // 1. Cek apakah ada user HR
    $hrUser = DB::table('users')->where('role', 'HR')->first();
    if (!$hrUser) {
        echo "❌ No HR user found!\n";
        exit;
    }
    echo "✅ HR User found: " . $hrUser->name . " (" . $hrUser->email . ")\n";
    
    // 2. Login sebagai user HR
    echo "\n=== Login as HR User ===\n";
    
    // Buat user instance untuk login
    $user = new \App\Models\User();
    $user->id = $hrUser->id;
    $user->name = $hrUser->name;
    $user->email = $hrUser->email;
    $user->role = $hrUser->role;
    
    // Login user
    Auth::login($user);
    
    if (Auth::check()) {
        echo "✅ User logged in successfully\n";
        echo "   User ID: " . Auth::id() . "\n";
        echo "   User Role: " . Auth::user()->role . "\n";
    } else {
        echo "❌ Failed to login user\n";
        exit;
    }
    
    // 3. Test POST /api/calendar dengan autentikasi
    echo "\n=== Testing POST /api/calendar with Auth ===\n";
    
    $holidayData = [
        'date' => '2024-12-31',
        'name' => 'Libur Tahun Baru Test',
        'description' => 'Test dengan autentikasi yang benar',
        'type' => 'custom'
    ];
    
    $request = new \Illuminate\Http\Request();
    $request->merge($holidayData);
    
    $controller = new \App\Http\Controllers\NationalHolidayController();
    
    try {
        $response = $controller->store($request);
        echo "✅ POST /api/calendar successful!\n";
        $data = json_decode($response->getContent(), true);
        echo "   Status Code: " . $response->getStatusCode() . "\n";
        echo "   Message: " . $data['message'] . "\n";
        echo "   Holiday ID: " . $data['data']['id'] . "\n";
        echo "   Holiday Name: " . $data['data']['name'] . "\n";
    } catch (Exception $e) {
        echo "❌ POST /api/calendar failed: " . $e->getMessage() . "\n";
    }
    
    // 4. Verifikasi di database
    echo "\n=== Database Verification ===\n";
    $newHoliday = DB::table('national_holidays')
        ->where('date', '2024-12-31')
        ->first();
    
    if ($newHoliday) {
        echo "✅ New holiday found in database:\n";
        echo "   ID: " . $newHoliday->id . "\n";
        echo "   Name: " . $newHoliday->name . "\n";
        echo "   Date: " . $newHoliday->date . "\n";
        echo "   Type: " . $newHoliday->type . "\n";
        echo "   Created by: " . $newHoliday->created_by . "\n";
    } else {
        echo "❌ New holiday not found in database\n";
    }
    
    // 5. Logout
    Auth::logout();
    echo "\n✅ User logged out\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "If this test passes, the backend is working correctly.\n";
echo "The frontend issue is likely due to:\n";
echo "1. Wrong URL endpoint\n";
echo "2. Missing Authorization header\n";
echo "3. Invalid token\n";
echo "4. CORS issue\n";
?> 