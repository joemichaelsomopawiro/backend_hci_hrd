<?php

use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrProgramCrew;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING CREW MANAGEMENT ===\n";

// 1. Get a Program (or create one)
$program = PrProgram::first();
if (!$program) {
    echo "No program found. Please run test_create_program.php first or create one manually.\n";
    exit(1);
}
echo "[INFO] Using Program ID: {$program->id} ({$program->name})\n";

// 2. Get a User to be a Crew (e.g., Creative)
$crewUser = User::where('email', 'creative@joe.com')->first();
if (!$crewUser) {
    echo "User creative@joe.com not found. Please run seeder.\n";
    exit(1);
}
echo "[INFO] Using User ID: {$crewUser->id} ({$crewUser->name})\n";

// 3. Clear existing crew for this user on this program (cleanup)
PrProgramCrew::where('program_id', $program->id)->where('user_id', $crewUser->id)->delete();

// 4. Simulate POST request to add crew
echo "\n--- ADDING CREW ---\n";
// Manually calling controller logic or using Http client simulation isn't easy in raw php script, 
// so we'll test the Model/DB logic directly OR try to make a request if we had a proper test suite.
// Since we are in a script, let's just use curl to hit the local API to verify ROUTES too.

$baseUrl = 'http://127.0.0.1:8000/api';

// Need a token for Manager Program
$manager = User::where('role', 'Program Manager')->first();
$token = $manager->createToken('TestToken')->plainTextToken;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/manager-program/programs/{$program->id}/team-members");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'user_id' => $crewUser->id,
    'role' => 'Kreatif'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode != 201) {
    echo "[FAIL] Failed to add crew.\n";
    exit(1);
}
echo "[PASS] Crew added successfully.\n";

$json = json_decode($response, true);
$memberId = $json['data']['id'] ?? null;

if (!$memberId) {
    echo "Could not get Member ID from response.\n";
    exit(1);
}

// 5. Simulate GET request to list crew
echo "\n--- LISTING CREW ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/manager-program/programs/{$program->id}/team-members");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
// echo "Response: {$response}\n"; // Too verbose

$crews = json_decode($response, true)['data'] ?? [];
$found = false;
foreach ($crews as $c) {
    if ($c['id'] == $memberId && $c['user_id'] == $crewUser->id) {
        $found = true;
        echo "Found crew member: " . $c['user']['name'] . " as " . $c['role'] . "\n";
        break;
    }
}

if (!$found) {
    echo "[FAIL] Added crew member not found in list.\n";
    exit(1);
}
echo "[PASS] Crew listing verified.\n";

// 6. Simulate DELETE request
echo "\n--- DELETING CREW ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/manager-program/programs/{$program->id}/team-members/{$memberId}");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode != 200) {
    echo "[FAIL] Failed to delete crew.\n";
    exit(1);
}
echo "[PASS] Crew deleted successfully.\n";

echo "\n=== ALL TESTS PASSED ===\n";
