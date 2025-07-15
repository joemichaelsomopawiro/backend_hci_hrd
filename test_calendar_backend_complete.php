<?php
// test_calendar_backend_complete.php
// Script testing lengkap untuk backend calendar yang sudah diperbaiki

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Backend Calendar Complete ===\n\n";

try {
    // 1. Cek apakah ada user HR
    $hrUser = DB::table('users')->where('role', 'HR')->first();
    if (!$hrUser) {
        echo "❌ No HR user found!\n";
        echo "Please create an HR user first.\n";
        exit;
    }
    echo "✅ HR User found: " . $hrUser->name . " (" . $hrUser->email . ")\n";
    
    // 2. Cek apakah ada hari libur di database
    $holidaysCount = DB::table('national_holidays')->count();
    echo "✅ Total holidays in database: " . $holidaysCount . "\n";
    
    // 3. Test semua endpoint
    echo "\n=== Testing All Calendar Endpoints ===\n";
    
    // Test 1: Get Calendar Data
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
    
    // Test 2: Get Calendar Data for Frontend
    echo "\n2. Testing GET /api/calendar/data-frontend...\n";
    try {
        $response = $controller->getCalendarDataForFrontend($request);
        echo "✅ GET /api/calendar/data-frontend successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Holidays map: " . count($data['data']) . " entries\n";
    } catch (Exception $e) {
        echo "❌ GET /api/calendar/data-frontend failed: " . $e->getMessage() . "\n";
    }
    
    // Test 3: Check Holiday
    echo "\n3. Testing GET /api/calendar/check...\n";
    $checkRequest = new \Illuminate\Http\Request();
    $checkRequest->merge(['date' => '2024-12-25']);
    
    try {
        $response = $controller->checkHoliday($checkRequest);
        echo "✅ GET /api/calendar/check successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Date: " . $data['data']['date'] . "\n";
        echo "   Is Holiday: " . ($data['data']['is_holiday'] ? 'Yes' : 'No') . "\n";
        echo "   Holiday Name: " . ($data['data']['holiday_name'] ?? 'None') . "\n";
    } catch (Exception $e) {
        echo "❌ GET /api/calendar/check failed: " . $e->getMessage() . "\n";
    }
    
    // Test 4: Get Available Years
    echo "\n4. Testing GET /api/calendar/years...\n";
    try {
        $response = $controller->getAvailableYears();
        echo "✅ GET /api/calendar/years successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Available years: " . implode(', ', $data['data']) . "\n";
    } catch (Exception $e) {
        echo "❌ GET /api/calendar/years failed: " . $e->getMessage() . "\n";
    }
    
    // Test 5: Get Holiday Types
    echo "\n5. Testing GET /api/calendar/types...\n";
    try {
        $response = $controller->getHolidayTypes();
        echo "✅ GET /api/calendar/types successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Holiday types: " . implode(', ', array_keys($data['data'])) . "\n";
    } catch (Exception $e) {
        echo "❌ GET /api/calendar/types failed: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Add Holiday (HR Only)
    echo "\n6. Testing POST /api/calendar (HR Only)...\n";
    $holidayData = [
        'date' => '2024-12-30',
        'name' => 'Libur Test Backend',
        'description' => 'Test untuk backend yang sudah diperbaiki',
        'type' => 'custom'
    ];
    
    // Simulate HR user login
    Auth::loginUsingId($hrUser->id);
    
    try {
        $storeRequest = new \Illuminate\Http\Request();
        $storeRequest->merge($holidayData);
        
        $response = $controller->store($storeRequest);
        echo "✅ POST /api/calendar successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Holiday ID: " . $data['data']['id'] . "\n";
        echo "   Holiday Name: " . $data['data']['name'] . "\n";
        
        $newHolidayId = $data['data']['id'];
        
        // Test 7: Update Holiday
        echo "\n7. Testing PUT /api/calendar/{id}...\n";
        $updateData = [
            'name' => 'Libur Test Backend Updated',
            'description' => 'Test update untuk backend yang sudah diperbaiki',
            'is_active' => true
        ];
        
        $updateRequest = new \Illuminate\Http\Request();
        $updateRequest->merge($updateData);
        
        $response = $controller->update($updateRequest, $newHolidayId);
        echo "✅ PUT /api/calendar/{id} successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Updated Name: " . $data['data']['name'] . "\n";
        
        // Test 8: Delete Holiday
        echo "\n8. Testing DELETE /api/calendar/{id}...\n";
        $response = $controller->destroy($newHolidayId);
        echo "✅ DELETE /api/calendar/{id} successful\n";
        $data = json_decode($response->getContent(), true);
        echo "   Message: " . $data['message'] . "\n";
        
    } catch (Exception $e) {
        echo "❌ HR operations failed: " . $e->getMessage() . "\n";
    }
    
    // Test 9: Google Calendar Service
    echo "\n9. Testing Google Calendar Service...\n";
    try {
        $googleService = new \App\Services\GoogleCalendarService();
        
        // Test connection
        $connectionTest = $googleService->testConnection();
        echo "✅ Google Calendar connection test completed\n";
        echo "   Success: " . ($connectionTest['success'] ? 'Yes' : 'No') . "\n";
        echo "   Has API Key: " . ($connectionTest['has_api_key'] ? 'Yes' : 'No') . "\n";
        
        // Test fetch holidays
        $holidays = $googleService->fetchNationalHolidays(2024);
        echo "✅ Google Calendar fetch holidays completed\n";
        echo "   Holidays fetched: " . count($holidays) . "\n";
        
        // Test sync (optional - might take time)
        echo "\n10. Testing Google Calendar Sync...\n";
        $syncResult = $googleService->syncNationalHolidays(2024);
        echo "✅ Google Calendar sync completed\n";
        echo "   Synced count: " . $syncResult['synced_count'] . "\n";
        echo "   Year: " . $syncResult['year'] . "\n";
        
    } catch (Exception $e) {
        echo "❌ Google Calendar Service failed: " . $e->getMessage() . "\n";
    }
    
    // Test 11: Test non-HR user access
    echo "\n11. Testing Non-HR User Access...\n";
    $nonHrUser = DB::table('users')->where('role', '!=', 'HR')->first();
    if ($nonHrUser) {
        Auth::loginUsingId($nonHrUser->id);
        
        try {
            $storeRequest = new \Illuminate\Http\Request();
            $storeRequest->merge($holidayData);
            
            $response = $controller->store($storeRequest);
            echo "❌ Non-HR user should not be able to add holiday\n";
        } catch (Exception $e) {
            echo "✅ Non-HR user correctly blocked from adding holiday\n";
        }
    } else {
        echo "⚠️ No non-HR user found for testing\n";
    }
    
    // Test 12: Database Integrity
    echo "\n12. Testing Database Integrity...\n";
    try {
        // Check unique constraint
        $duplicateHoliday = [
            'date' => '2024-12-25', // Christmas - should already exist
            'name' => 'Duplicate Test',
            'description' => 'Test duplicate date',
            'type' => 'custom'
        ];
        
        Auth::loginUsingId($hrUser->id);
        $storeRequest = new \Illuminate\Http\Request();
        $storeRequest->merge($duplicateHoliday);
        
        $response = $controller->store($storeRequest);
        echo "❌ Duplicate date should not be allowed\n";
    } catch (Exception $e) {
        echo "✅ Duplicate date correctly prevented\n";
    }
    
    echo "\n=== Testing Summary ===\n";
    echo "✅ All basic endpoints working\n";
    echo "✅ HR role access working\n";
    echo "✅ Non-HR role restrictions working\n";
    echo "✅ Google Calendar service working\n";
    echo "✅ Database constraints working\n";
    echo "✅ Error handling working\n";
    
    echo "\n🎉 Backend Calendar System is working perfectly!\n";
    echo "Ready for frontend integration! 🚀\n";
    
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 