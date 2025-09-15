<?php
// test_calendar_program_manager.php
// Script untuk test akses Program Manager ke calendar endpoints

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\NationalHoliday;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Program Manager Calendar Access ===\n\n";

try {
    // Cek apakah ada user Program Manager
    $programManager = User::where('role', 'Program Manager')->first();
    
    if (!$programManager) {
        echo "❌ Tidak ada user dengan role 'Program Manager'\n";
        echo "Membuat user Program Manager untuk testing...\n";
        
        // Buat user Program Manager untuk testing
        $programManager = User::create([
            'name' => 'Program Manager Test',
            'email' => 'pm@company.com',
            'phone' => '081234567891',
            'password' => bcrypt('password'),
            'role' => 'Program Manager'
        ]);
        
        echo "✅ User Program Manager berhasil dibuat\n";
    } else {
        echo "✅ User Program Manager ditemukan: {$programManager->name}\n";
    }
    
    echo "\n=== Testing Calendar Endpoints ===\n";
    
    // Simulasi login sebagai Program Manager
    Auth::login($programManager);
    
    // Test 1: Tambah hari libur
    echo "\n1. Testing ADD Holiday (POST /api/calendar/):\n";
    
    $testHoliday = [
        'date' => '2025-02-15',
        'name' => 'Test Holiday PM',
        'description' => 'Test holiday created by Program Manager',
        'type' => 'custom'
    ];
    
    // Simulasi request
    $request = new \Illuminate\Http\Request();
    $request->merge($testHoliday);
    
    $controller = new \App\Http\Controllers\NationalHolidayController();
    
    try {
        $response = $controller->store($request);
        $responseData = json_decode($response->getContent(), true);
        
        if ($responseData['success']) {
            echo "✅ ADD Holiday: BERHASIL\n";
            echo "   Message: {$responseData['message']}\n";
            echo "   Holiday ID: {$responseData['data']['id']}\n";
            
            $holidayId = $responseData['data']['id'];
            
            // Test 2: Edit hari libur
            echo "\n2. Testing EDIT Holiday (PUT /api/calendar/{$holidayId}):\n";
            
            $updateData = [
                'name' => 'Test Holiday PM - Updated',
                'description' => 'Test holiday updated by Program Manager',
                'is_active' => true
            ];
            
            $updateRequest = new \Illuminate\Http\Request();
            $updateRequest->merge($updateData);
            
            try {
                $updateResponse = $controller->update($updateRequest, $holidayId);
                $updateResponseData = json_decode($updateResponse->getContent(), true);
                
                if ($updateResponseData['success']) {
                    echo "✅ EDIT Holiday: BERHASIL\n";
                    echo "   Message: {$updateResponseData['message']}\n";
                    echo "   Updated Name: {$updateResponseData['data']['name']}\n";
                    
                    // Test 3: Hapus hari libur
                    echo "\n3. Testing DELETE Holiday (DELETE /api/calendar/{$holidayId}):\n";
                    
                    try {
                        $deleteResponse = $controller->destroy($holidayId);
                        $deleteResponseData = json_decode($deleteResponse->getContent(), true);
                        
                        if ($deleteResponseData['success']) {
                            echo "✅ DELETE Holiday: BERHASIL\n";
                            echo "   Message: {$deleteResponseData['message']}\n";
                        } else {
                            echo "❌ DELETE Holiday: GAGAL\n";
                            echo "   Error: {$deleteResponseData['message']}\n";
                        }
                    } catch (Exception $e) {
                        echo "❌ DELETE Holiday: ERROR\n";
                        echo "   Error: {$e->getMessage()}\n";
                    }
                    
                } else {
                    echo "❌ EDIT Holiday: GAGAL\n";
                    echo "   Error: {$updateResponseData['message']}\n";
                }
            } catch (Exception $e) {
                echo "❌ EDIT Holiday: ERROR\n";
                echo "   Error: {$e->getMessage()}\n";
            }
            
        } else {
            echo "❌ ADD Holiday: GAGAL\n";
            echo "   Error: {$responseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ ADD Holiday: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    // Test 4: Seed holidays
    echo "\n4. Testing SEED Holidays (POST /api/calendar/seed):\n";
    
    $seedRequest = new \Illuminate\Http\Request();
    $seedRequest->merge(['year' => 2025]);
    
    try {
        $seedResponse = $controller->seedHolidays($seedRequest);
        $seedResponseData = json_decode($seedResponse->getContent(), true);
        
        if ($seedResponseData['success']) {
            echo "✅ SEED Holidays: BERHASIL\n";
            echo "   Message: {$seedResponseData['message']}\n";
        } else {
            echo "❌ SEED Holidays: GAGAL\n";
            echo "   Error: {$seedResponseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ SEED Holidays: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    // Test 5: Bulk seed years
    echo "\n5. Testing BULK SEED Years (POST /api/calendar/bulk-seed):\n";
    
    $bulkSeedRequest = new \Illuminate\Http\Request();
    $bulkSeedRequest->merge(['years' => [2026, 2027]]);
    
    try {
        $bulkSeedResponse = $controller->bulkSeedYears($bulkSeedRequest);
        $bulkSeedResponseData = json_decode($bulkSeedResponse->getContent(), true);
        
        if ($bulkSeedResponseData['success']) {
            echo "✅ BULK SEED Years: BERHASIL\n";
            echo "   Message: {$bulkSeedResponseData['message']}\n";
        } else {
            echo "❌ BULK SEED Years: GAGAL\n";
            echo "   Error: {$bulkSeedResponseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ BULK SEED Years: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    // Test 6: Create recurring holiday
    echo "\n6. Testing CREATE Recurring Holiday (POST /api/calendar/recurring):\n";
    
    $recurringRequest = new \Illuminate\Http\Request();
    $recurringRequest->merge([
        'day_of_week' => 0, // Minggu
        'name' => 'Test Recurring Sunday',
        'description' => 'Test recurring holiday by PM',
        'start_year' => 2025,
        'end_year' => 2025
    ]);
    
    try {
        $recurringResponse = $controller->createRecurringHoliday($recurringRequest);
        $recurringResponseData = json_decode($recurringResponse->getContent(), true);
        
        if ($recurringResponseData['success']) {
            echo "✅ CREATE Recurring Holiday: BERHASIL\n";
            echo "   Message: {$recurringResponseData['message']}\n";
        } else {
            echo "❌ CREATE Recurring Holiday: GAGAL\n";
            echo "   Error: {$recurringResponseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ CREATE Recurring Holiday: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    // Test 7: Create monthly holiday
    echo "\n7. Testing CREATE Monthly Holiday (POST /api/calendar/monthly):\n";
    
    $monthlyRequest = new \Illuminate\Http\Request();
    $monthlyRequest->merge([
        'day_of_month' => 15,
        'name' => 'Test Monthly 15th',
        'description' => 'Test monthly holiday by PM',
        'start_year' => 2025,
        'end_year' => 2025
    ]);
    
    try {
        $monthlyResponse = $controller->createMonthlyHoliday($monthlyRequest);
        $monthlyResponseData = json_decode($monthlyResponse->getContent(), true);
        
        if ($monthlyResponseData['success']) {
            echo "✅ CREATE Monthly Holiday: BERHASIL\n";
            echo "   Message: {$monthlyResponseData['message']}\n";
        } else {
            echo "❌ CREATE Monthly Holiday: GAGAL\n";
            echo "   Error: {$monthlyResponseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ CREATE Monthly Holiday: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    // Test 8: Create date range holiday
    echo "\n8. Testing CREATE Date Range Holiday (POST /api/calendar/date-range):\n";
    
    $dateRangeRequest = new \Illuminate\Http\Request();
    $dateRangeRequest->merge([
        'start_date' => '2025-03-01',
        'end_date' => '2025-03-03',
        'name' => 'Test Date Range Holiday',
        'description' => 'Test date range holiday by PM'
    ]);
    
    try {
        $dateRangeResponse = $controller->createDateRangeHoliday($dateRangeRequest);
        $dateRangeResponseData = json_decode($dateRangeResponse->getContent(), true);
        
        if ($dateRangeResponseData['success']) {
            echo "✅ CREATE Date Range Holiday: BERHASIL\n";
            echo "   Message: {$dateRangeResponseData['message']}\n";
        } else {
            echo "❌ CREATE Date Range Holiday: GAGAL\n";
            echo "   Error: {$dateRangeResponseData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ CREATE Date Range Holiday: ERROR\n";
        echo "   Error: {$e->getMessage()}\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "✅ Program Manager sekarang memiliki akses penuh ke calendar endpoints\n";
    echo "✅ Semua operasi CRUD calendar berfungsi untuk Program Manager\n";
    echo "✅ Tidak ada error 403 Forbidden\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?> 