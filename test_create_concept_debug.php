<?php

use App\Models\User;
use App\Models\PrProgram;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING CREATE CONCEPT ===\n";

// Get Manager Program user
$manager = User::where('role', 'Program Manager')->first();
if (!$manager) {
    echo "[FAIL] No Program Manager found.\n";
    exit(1);
}

echo "[INFO] Found Manager: {$manager->name} (ID: {$manager->id})\n";

// Get a program
$program = PrProgram::first();
if (!$program) {
    echo "[FAIL] No program found.\n";
    exit(1);
}

echo "[INFO] Using Program: {$program->name} (ID: {$program->id})\n";

// Simulate creating concept
$token = $manager->createToken('TestToken')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8000/api';

$data = [
    'concept' => 'Test Concept from Debug Script',
    'objectives' => 'Test objectives',
    'target_audience' => 'Test audience',
    'content_outline' => 'Test outline',
    'format_description' => 'Test format'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/program-regular/manager-program/programs/{$program->id}/concepts");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nHTTP Code: {$httpCode}\n";
echo "Response:\n";
echo $response . "\n";

if ($httpCode == 201) {
    echo "\n[PASS] Concept created successfully!\n";
} else {
    echo "\n[FAIL] Failed to create concept.\n";

    // Try to decode error
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['message'])) {
        echo "Error Message: " . $decoded['message'] . "\n";
    }
}

echo "\n=== DONE ===\n";
