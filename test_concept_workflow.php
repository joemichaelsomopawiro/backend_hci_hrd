<?php

use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING NEW CONCEPT WORKFLOW ===\n\n";

// Get Manager and Producer
$manager = User::where('role', 'Program Manager')->first();
$producer = User::where('role', 'Producer')->first();

if (!$manager || !$producer) {
    echo "[FAIL] Need both Manager and Producer users.\n";
    exit(1);
}

echo "[INFO] Manager: {$manager->name} (ID: {$manager->id})\n";
echo "[INFO] Producer: {$producer->name} (ID: {$producer->id})\n\n";

$program = PrProgram::first();
$baseUrl = 'http://127.0.0.1:8000/api/program-regular';

// Test 1: Manager creates concept
echo "--- TEST 1: Create Concept ---\n";
$managerToken = $manager->createToken('Test')->plainTextToken;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/manager-program/programs/{$program->id}/concepts");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'concept' => 'Test concept for new workflow',
    'objectives' => 'Test objectives'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $managerToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 201) {
    $concept = json_decode($response, true)['data'];
    $conceptId = $concept['id'];
    echo "[PASS] Concept created (ID: {$conceptId})\n\n";
} else {
    echo "[FAIL] Failed to create concept. HTTP {$httpCode}\n";
    echo "Response: {$response}\n";
    exit(1);
}

// Test 2: Manager edits concept
echo "--- TEST 2: Edit Concept ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/manager-program/programs/{$program->id}/concepts/{$conceptId}");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'concept' => 'UPDATED concept text',
    'objectives' => 'Updated objectives'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $managerToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "[PASS] Concept updated successfully\n\n";
} else {
    echo "[FAIL] Failed to update. HTTP {$httpCode}\n";
    echo "Response: {$response}\n\n";
}

// Test 3: Producer marks as read
echo "--- TEST 3: Producer Marks as Read ---\n";
$producerToken = $producer->createToken('Test')->plainTextToken;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/producer/concepts/{$conceptId}/mark-as-read");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $producerToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $concept = json_decode($response, true)['data'];
    echo "[PASS] Concept marked as read\n";
    echo "Read by: {$concept['reader']['name']}\n";
    echo "Read at: {$concept['read_at']}\n\n";
} else {
    echo "[FAIL] Failed to mark as read. HTTP {$httpCode}\n";
    echo "Response: {$response}\n\n";
}

// Test 4: Verify read status persisted
echo "--- TEST 4: Verify Read Status ---\n";
$dbConcept = PrProgramConcept::find($conceptId);
if ($dbConcept->read_by == $producer->id && $dbConcept->read_at) {
    echo "[PASS] Read status persisted in database\n";
    echo "is_read attribute: " . ($dbConcept->is_read ? 'true' : 'false') . "\n\n";
} else {
    echo "[FAIL] Read status not persisted\n\n";
}

// Test 5: Manager deletes concept
echo "--- TEST 5: Delete Concept ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/manager-program/programs/{$program->id}/concepts/{$conceptId}");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $managerToken,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "[PASS] Concept deleted successfully\n\n";
} else {
    echo "[FAIL] Failed to delete. HTTP {$httpCode}\n";
    echo "Response: {$response}\n\n";
}

echo "=== ALL TESTS COMPLETE ===\n";
