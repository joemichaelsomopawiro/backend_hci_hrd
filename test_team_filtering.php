<?php

use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrProgramCrew;
use App\Models\PrProgramConcept;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING TEAM-BASED FILTERING ===\n";

// 1. Get test users
$producer1 = User::where('email', 'producer@joe.com')->first();
$producer2 = User::where('email', 'producer@manager.com')->first(); // Alternative if exists

if (!$producer1) {
    echo "[FAIL] Producer user not found. Please ensure producer@joe.com exists.\n";
    exit(1);
}

echo "[INFO] Found Producer 1: {$producer1->name} (ID: {$producer1->id})\n";

// 2. Get or create test programs
$program1 = PrProgram::first();
$program2 = PrProgram::skip(1)->first();

if (!$program1 || !$program2) {
    echo "[FAIL] Need at least 2 programs. Please create programs first.\n";
    exit(1);
}

echo "[INFO] Program 1: {$program1->name} (ID: {$program1->id})\n";
echo "[INFO] Program 2: {$program2->name} (ID: {$program2->id})\n";

// 3. Clean existing crew assignments for these programs
PrProgramCrew::whereIn('program_id', [$program1->id, $program2->id])
    ->where('user_id', $producer1->id)
    ->delete();

echo "\n--- SCENARIO 1: Producer NOT assigned to any program ---\n";

// Test API endpoint
$token = $producer1->createToken('TestToken')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8000/api';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/producer/concepts");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo "[FAIL] HTTP Code {$httpCode}\n";
    echo "Response: {$response}\n";
} else {
    $json = json_decode($response, true);
    $concepts = $json['data']['data'] ?? [];
    echo "Found " . count($concepts) . " concepts.\n";
    if (count($concepts) == 0) {
        echo "[PASS] Producer sees 0 concepts when not assigned to any program.\n";
    } else {
        echo "[FAIL] Producer should see 0 concepts but saw " . count($concepts) . "\n";
    }
}

// 4. Assign Producer to Program 1 only
echo "\n--- SCENARIO 2: Assign Producer to Program 1 only ---\n";
PrProgramCrew::create([
    'program_id' => $program1->id,
    'user_id' => $producer1->id,
    'role' => 'Producer'
]);
echo "[INFO] Assigned Producer to Program 1.\n";

// Create concept for Program 1
$concept1 = PrProgramConcept::where('program_id', $program1->id)->first();
if (!$concept1) {
    $concept1 = PrProgramConcept::create([
        'program_id' => $program1->id,
        'concept' => 'Test Concept for Program 1',
        'status' => 'pending_approval',
        'created_by' => 1
    ]);
    echo "[INFO] Created concept for Program 1.\n";
} else {
    // Update to pending
    $concept1->update(['status' => 'pending_approval']);
    echo "[INFO] Updated existing concept for Program 1 to pending.\n";
}

// Test again
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/producer/concepts");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo "[FAIL] HTTP Code {$httpCode}\n";
} else {
    $json = json_decode($response, true);
    $concepts = $json['data']['data'] ?? [];
    echo "Found " . count($concepts) . " concepts.\n";

    $foundProgram1 = false;
    $foundProgram2 = false;

    foreach ($concepts as $concept) {
        if ($concept['program_id'] == $program1->id) {
            $foundProgram1 = true;
        }
        if ($concept['program_id'] == $program2->id) {
            $foundProgram2 = true;
        }
    }

    if ($foundProgram1 && !$foundProgram2) {
        echo "[PASS] Producer sees only Program 1 (assigned program).\n";
    } else {
        echo "[FAIL] Producer should see Program 1 only.\n";
        echo "  Found Program 1: " . ($foundProgram1 ? 'YES' : 'NO') . "\n";
        echo "  Found Program 2: " . ($foundProgram2 ? 'YES' : 'NO') . "\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
