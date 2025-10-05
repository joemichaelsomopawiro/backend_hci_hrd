<?php
// Debug script untuk unified endpoints

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Debug Unified Endpoints ===\n\n";

try {
    // Test 1: Check if Song model exists
    echo "1. Testing Song model...\n";
    $songCount = \App\Models\Song::count();
    echo "   ✅ Found {$songCount} songs in database\n";
    
    $availableSongs = \App\Models\Song::available()->count();
    echo "   ✅ Found {$availableSongs} available songs\n\n";
    
    // Test 2: Check if Singer model exists
    echo "2. Testing Singer model...\n";
    $singerCount = \App\Models\Singer::count();
    echo "   ✅ Found {$singerCount} singers in database\n";
    
    $activeSingers = \App\Models\Singer::active()->count();
    echo "   ✅ Found {$activeSingers} active singers\n\n";
    
    // Test 3: Check User model with Singer role
    echo "3. Testing User model with Singer role...\n";
    $userSingerCount = \App\Models\User::where('role', 'Singer')->count();
    echo "   ✅ Found {$userSingerCount} users with Singer role\n\n";
    
    // Test 4: Test unified songs logic
    echo "4. Testing unified songs logic...\n";
    $query = \App\Models\Song::available();
    $songs = $query->orderBy('title')->get();
    echo "   ✅ Unified songs query successful: {$songs->count()} songs\n\n";
    
    // Test 5: Test unified singers logic
    echo "5. Testing unified singers logic...\n";
    $singerQuery = \App\Models\Singer::active();
    $singers = $singerQuery->orderBy('name')->get();
    $userQuery = \App\Models\User::where('role', 'Singer');
    $userSingers = $userQuery->orderBy('name')->get();
    echo "   ✅ Singer model query successful: {$singers->count()} singers\n";
    echo "   ✅ User model query successful: {$userSingers->count()} singers\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n\n";
}

echo "=== Debug Complete ===\n";

