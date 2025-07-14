<?php
// test_calendar_simple.php
// Script test sederhana untuk calendar endpoint

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Simple Calendar Test ===\n\n";

try {
    // Cek apakah ada user HR
    $hrUser = DB::table('users')->where('role', 'HR')->first();
    echo "HR User: " . ($hrUser ? $hrUser->name : 'Not found') . "\n";
    
    // Cek apakah ada hari libur di database
    $holidays = DB::table('national_holidays')->count();
    echo "Total holidays in database: " . $holidays . "\n";
    
    // Cek apakah ada hari libur untuk tanggal test
    $testDate = '2024-12-26';
    $existingHoliday = DB::table('national_holidays')->where('date', $testDate)->first();
    echo "Holiday for {$testDate}: " . ($existingHoliday ? $existingHoliday->name : 'Not found') . "\n";
    
    // Test insert manual
    echo "\n=== Testing Manual Insert ===\n";
    
    if (!$existingHoliday) {
        $inserted = DB::table('national_holidays')->insert([
            'date' => $testDate,
            'name' => 'Libur Test Manual',
            'description' => 'Test insert manual',
            'type' => 'custom',
            'is_active' => true,
            'created_by' => $hrUser->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        if ($inserted) {
            echo "✅ Manual insert successful!\n";
        } else {
            echo "❌ Manual insert failed!\n";
        }
    } else {
        echo "Holiday already exists for {$testDate}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?> 