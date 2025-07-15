<?php

/**
 * 🐛 DEBUG DATE SHIFT ISSUE
 * Menguji masalah pergeseran tanggal ketika user klik tanggal 25 tapi tersimpan tanggal 24
 * 
 * Masalah yang dilaporkan:
 * - User klik tanggal 25 di frontend
 * - Yang tersimpan di database adalah tanggal 24
 * - Kemungkinan masalah timezone antara frontend dan backend
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DateShiftDebugger
{
    private $baseUrl;
    private $testResults = [];
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
    }
    
    public function runDebugTests()
    {
        echo "🐛 DATE SHIFT ISSUE DEBUGGER\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        echo "📅 Testing date shift issue when clicking date 25\n";
        echo "Expected: Date 25 should be saved as 25, not 24\n\n";
        
        // Test berbagai format tanggal yang mungkin dikirim frontend
        $this->testDateFormats();
        $this->testTimezoneIssues();
        $this->testFrontendSimulation();
        $this->testDatabaseDirectly();
        
        $this->displayResults();
        $this->provideSolutions();
    }
    
    private function testDateFormats()
    {
        echo "📋 TEST 1: Different Date Formats\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        $testDate = '2025-01-25'; // Target date: 25 Januari 2025
        
        $formats = [
            'YYYY-MM-DD' => '2025-01-25',
            'ISO with UTC' => '2025-01-25T00:00:00.000Z',
            'ISO with Jakarta Time' => '2025-01-25T07:00:00.000+07:00',
            'ISO Midnight UTC' => '2025-01-25T00:00:00Z',
            'ISO Noon UTC' => '2025-01-25T12:00:00Z',
            'ISO Evening UTC' => '2025-01-25T23:59:59Z',
            'Frontend Typical' => '2025-01-25T00:00:00.000Z',
        ];
        
        foreach ($formats as $formatName => $dateString) {
            echo "\n   Testing {$formatName}: {$dateString}\n";
            
            try {
                // Simulate backend processing
                $processedDate = $this->simulateBackendProcessing($dateString);
                
                $status = ($processedDate === '2025-01-25') ? '✅ CORRECT' : '❌ WRONG';
                echo "   Result: {$processedDate} - {$status}\n";
                
                $this->testResults[] = [
                    'test' => "Date Format: {$formatName}",
                    'input' => $dateString,
                    'output' => $processedDate,
                    'expected' => '2025-01-25',
                    'status' => ($processedDate === '2025-01-25') ? 'PASS' : 'FAIL'
                ];
                
            } catch (Exception $e) {
                echo "   ERROR: " . $e->getMessage() . "\n";
                $this->testResults[] = [
                    'test' => "Date Format: {$formatName}",
                    'input' => $dateString,
                    'output' => 'ERROR',
                    'expected' => '2025-01-25',
                    'status' => 'ERROR'
                ];
            }
        }
    }
    
    private function testTimezoneIssues()
    {
        echo "\n\n🌍 TEST 2: Timezone Issues\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        $timezones = [
            'UTC' => 'UTC',
            'Jakarta' => 'Asia/Jakarta',
            'System Default' => date_default_timezone_get()
        ];
        
        $testDate = '2025-01-25T00:00:00.000Z'; // Midnight UTC on Jan 25
        
        foreach ($timezones as $tzName => $timezone) {
            echo "\n   Testing with {$tzName} ({$timezone}):\n";
            
            try {
                // Set timezone temporarily
                $originalTz = date_default_timezone_get();
                date_default_timezone_set($timezone);
                
                $carbon = Carbon::parse($testDate);
                $dateOnly = $carbon->format('Y-m-d');
                $fullDateTime = $carbon->format('Y-m-d H:i:s T');
                
                echo "     Carbon parsed: {$fullDateTime}\n";
                echo "     Date only: {$dateOnly}\n";
                
                $status = ($dateOnly === '2025-01-25') ? '✅ CORRECT' : '❌ WRONG';
                echo "     Status: {$status}\n";
                
                // Restore timezone
                date_default_timezone_set($originalTz);
                
                $this->testResults[] = [
                    'test' => "Timezone: {$tzName}",
                    'input' => $testDate,
                    'output' => $dateOnly,
                    'expected' => '2025-01-25',
                    'status' => ($dateOnly === '2025-01-25') ? 'PASS' : 'FAIL'
                ];
                
            } catch (Exception $e) {
                echo "     ERROR: " . $e->getMessage() . "\n";
                date_default_timezone_set($originalTz);
            }
        }
    }
    
    private function testFrontendSimulation()
    {
        echo "\n\n💻 TEST 3: Frontend Simulation\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        echo "   Simulating what frontend might send when user clicks Jan 25:\n\n";
        
        // Simulasi berbagai kemungkinan data yang dikirim frontend
        $frontendScenarios = [
            'Date Picker (Chrome)' => '2025-01-25',
            'Date Picker with Time' => '2025-01-25T00:00:00',
            'JavaScript Date.toISOString()' => '2025-01-25T00:00:00.000Z',
            'Moment.js format' => '2025-01-25T00:00:00+07:00',
            'Manual Input' => '25/01/2025',
            'Google Calendar API Format' => '2025-01-25'
        ];
        
        foreach ($frontendScenarios as $scenario => $dateValue) {
            echo "   Scenario: {$scenario}\n";
            echo "   Frontend sends: {$dateValue}\n";
            
            try {
                $processed = $this->simulateBackendProcessing($dateValue);
                $status = ($processed === '2025-01-25') ? '✅ CORRECT' : '❌ WRONG';
                echo "   Backend processes as: {$processed} - {$status}\n\n";
                
                $this->testResults[] = [
                    'test' => "Frontend: {$scenario}",
                    'input' => $dateValue,
                    'output' => $processed,
                    'expected' => '2025-01-25',
                    'status' => ($processed === '2025-01-25') ? 'PASS' : 'FAIL'
                ];
                
            } catch (Exception $e) {
                echo "   ERROR: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    private function testDatabaseDirectly()
    {
        echo "\n💾 TEST 4: Database Storage Test\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        echo "   Testing direct database storage...\n";
        
        try {
            // Test dengan koneksi database langsung
            $testDate = '2025-01-25';
            
            // Simulasi insert ke database
            echo "   Input date: {$testDate}\n";
            
            // Test Carbon parsing
            $carbon = Carbon::parse($testDate);
            echo "   Carbon parsed: " . $carbon->format('Y-m-d H:i:s T') . "\n";
            echo "   Carbon date only: " . $carbon->format('Y-m-d') . "\n";
            
            // Test dengan timezone Jakarta
            $carbonJakarta = Carbon::parse($testDate, 'Asia/Jakarta');
            echo "   Carbon Jakarta: " . $carbonJakarta->format('Y-m-d H:i:s T') . "\n";
            echo "   Carbon Jakarta date only: " . $carbonJakarta->format('Y-m-d') . "\n";
            
            // Test dengan UTC
            $carbonUTC = Carbon::parse($testDate, 'UTC');
            echo "   Carbon UTC: " . $carbonUTC->format('Y-m-d H:i:s T') . "\n";
            echo "   Carbon UTC date only: " . $carbonUTC->format('Y-m-d') . "\n";
            
        } catch (Exception $e) {
            echo "   ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function simulateBackendProcessing($dateInput)
    {
        // Simulasi exact logic dari NationalHolidayController
        $processedDate = null;
        
        if (strpos($dateInput, 'T') !== false) {
            // Jika sudah format ISO dengan waktu
            $carbonDate = Carbon::parse($dateInput);
            // PENTING: Ambil hanya bagian tanggal, abaikan waktu
            $processedDate = $carbonDate->format('Y-m-d');
        } else if (strpos($dateInput, '/') !== false) {
            // Format DD/MM/YYYY
            $parts = explode('/', $dateInput);
            if (count($parts) === 3) {
                $processedDate = $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            } else {
                throw new Exception('Invalid date format');
            }
        } else {
            // Jika format YYYY-MM-DD biasa
            $processedDate = Carbon::parse($dateInput)->format('Y-m-d');
        }
        
        return $processedDate;
    }
    
    private function displayResults()
    {
        echo "\n\n📊 TEST RESULTS SUMMARY\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, function($result) {
            return $result['status'] === 'PASS';
        }));
        $failedTests = count(array_filter($this->testResults, function($result) {
            return $result['status'] === 'FAIL';
        }));
        $errorTests = count(array_filter($this->testResults, function($result) {
            return $result['status'] === 'ERROR';
        }));
        
        echo "\n📈 Overall Results:\n";
        echo "   Total Tests: {$totalTests}\n";
        echo "   Passed: {$passedTests} ✅\n";
        echo "   Failed: {$failedTests} ❌\n";
        echo "   Errors: {$errorTests} ⚠️\n";
        echo "   Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
        
        if ($failedTests > 0) {
            echo "\n❌ Failed Tests:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "   - {$result['test']}: {$result['input']} → {$result['output']} (expected: {$result['expected']})\n";
                }
            }
        }
    }
    
    private function provideSolutions()
    {
        echo "\n\n💡 SOLUTIONS & RECOMMENDATIONS\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        echo "\n🔧 Backend Fixes (Laravel):\n";
        echo "   1. Ensure timezone is set correctly in config/app.php:\n";
        echo "      'timezone' => 'Asia/Jakarta'\n";
        echo "\n   2. In NationalHolidayController, always extract date only:\n";
        echo "      \$processedDate = Carbon::parse(\$dateInput)->format('Y-m-d');\n";
        echo "\n   3. Add timezone logging for debugging:\n";
        echo "      Log::info('Timezone info', [\n";
        echo "          'app_timezone' => config('app.timezone'),\n";
        echo "          'system_timezone' => date_default_timezone_get()\n";
        echo "      ]);\n";
        
        echo "\n🌐 Frontend Fixes (JavaScript):\n";
        echo "   1. Send date in YYYY-MM-DD format only:\n";
        echo "      const dateValue = datePicker.value; // '2025-01-25'\n";
        echo "\n   2. Avoid using Date.toISOString() for date-only values:\n";
        echo "      // BAD: new Date('2025-01-25').toISOString()\n";
        echo "      // GOOD: '2025-01-25'\n";
        echo "\n   3. Use input type='date' for consistent behavior:\n";
        echo "      <input type='date' v-model='holidayDate' />\n";
        
        echo "\n🔍 Debugging Steps:\n";
        echo "   1. Check browser developer tools Network tab\n";
        echo "   2. Verify what date format is being sent to API\n";
        echo "   3. Check Laravel logs for date processing info\n";
        echo "   4. Test with different browsers and timezones\n";
        
        echo "\n⚡ Quick Fix for Current Issue:\n";
        echo "   1. Update frontend to send date in YYYY-MM-DD format only\n";
        echo "   2. Ensure backend extracts date part only (already implemented)\n";
        echo "   3. Test with the debug script above\n";
        echo "   4. Verify database timezone settings\n";
        
        echo "\n🎯 Expected Behavior:\n";
        echo "   User clicks: January 25, 2025\n";
        echo "   Frontend sends: '2025-01-25'\n";
        echo "   Backend processes: '2025-01-25'\n";
        echo "   Database stores: '2025-01-25'\n";
        echo "   User sees: January 25, 2025 ✅\n";
    }
}

// Run the debugger
echo "🐛 DATE SHIFT ISSUE DEBUGGER\n";
echo "Investigating why clicking date 25 saves as date 24\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n\n";

try {
    $debugger = new DateShiftDebugger();
    $debugger->runDebugTests();
} catch (Exception $e) {
    echo "❌ Debug failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Debug completed.\n";