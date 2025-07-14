<?php
// debug_frontend_calendar.php
// Script untuk debug masalah frontend calendar

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Frontend Calendar Issue ===\n\n";

try {
    // 1. Cek apakah ada user HR
    $hrUser = DB::table('users')->where('role', 'HR')->first();
    if (!$hrUser) {
        echo "❌ No HR user found!\n";
        exit;
    }
    echo "✅ HR User found: " . $hrUser->name . " (" . $hrUser->email . ")\n";
    
    // 2. Cek apakah ada hari libur di database
    $holidaysCount = DB::table('national_holidays')->count();
    echo "✅ Total holidays in database: " . $holidaysCount . "\n";
    
    // 3. Test endpoint dengan request yang sebenarnya
    echo "\n=== Testing Calendar Endpoints ===\n";
    
    // Test GET /api/calendar/data
    echo "1. Testing GET /api/calendar/data...\n";
    $request = new \Illuminate\Http\Request();
    $request->merge(['year' => '2024', 'month' => '12']);
    
    $controller = new \App\Http\Controllers\NationalHolidayController();
    try {
        $response = $controller->getCalendarData($request);
        echo "✅ GET /api/calendar/data successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Calendar days: " . count($data['data']['calendar']) . "\n";
        echo "   Holidays: " . count($data['data']['holidays']) . "\n";
    } catch (Exception $e) {
        echo "❌ GET /api/calendar/data failed: " . $e->getMessage() . "\n";
    }
    
    // Test POST /api/calendar
    echo "\n2. Testing POST /api/calendar...\n";
    $holidayData = [
        'date' => '2024-12-30',
        'name' => 'Libur Test Debug',
        'description' => 'Test untuk debug frontend issue',
        'type' => 'custom'
    ];
    
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
    
    try {
        $response = $controller->store($request);
        echo "✅ POST /api/calendar successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Message: " . $data['message'] . "\n";
        echo "   Holiday ID: " . $data['data']['id'] . "\n";
    } catch (Exception $e) {
        echo "❌ POST /api/calendar failed: " . $e->getMessage() . "\n";
        echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    // 4. Cek apakah ada hari libur yang baru dibuat
    echo "\n=== Verification ===\n";
    $newHoliday = DB::table('national_holidays')
        ->where('date', '2024-12-30')
        ->first();
    
    if ($newHoliday) {
        echo "✅ New holiday created: " . $newHoliday->name . "\n";
    } else {
        echo "❌ New holiday not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
echo "If backend tests pass, the issue is in frontend.\n";
echo "Check the frontend code for:\n";
echo "1. Wrong URL endpoint\n";
echo "2. Wrong HTTP method\n";
echo "3. Missing headers\n";
echo "4. Invalid JSON data\n";
?> 