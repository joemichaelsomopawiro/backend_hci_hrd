<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING ROLE MAPPING FILTERING ===\n";

// Login as manager to get token
$manager = User::where('role', 'Program Manager')->first();
$token = $manager->createToken('TestToken')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8000/api';

function testRole($roleInput, $expectedRoleInDB, $token, $baseUrl)
{
    echo "\nTesting Input: '{$roleInput}' (Expected DB Role: '{$expectedRoleInDB}')\n";
    $ch = curl_init();
    $encodedRole = urlencode($roleInput);
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/users?role={$encodedRole}");
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
        return;
    }

    $json = json_decode($response, true);
    $data = $json['data'] ?? [];

    echo "Found " . count($data) . " users.\n";
    $foundMatch = false;
    foreach ($data as $user) {
        $userRole = $user['role'];
        $jabatan = $user['employee']['jabatan_saat_ini'] ?? 'N/A';

        // Check if the returned user matches the EXPECTED DB Role (either in user role or jabatan)
        if (strcasecmp($userRole, $expectedRoleInDB) == 0 || strcasecmp($jabatan, $expectedRoleInDB) == 0) {
            $foundMatch = true;
        }
    }

    if ($foundMatch) {
        echo "[PASS] Successfully found users matching '{$expectedRoleInDB}' when searching for '{$roleInput}'.\n";
    } else {
        echo "[FAIL] No users matching '{$expectedRoleInDB}' found.\n";
    }
}

// 1. Test "Kreatif" -> should find "Creative"
testRole('Kreatif', 'Creative', $token, $baseUrl);

// 2. Test "Musik Arr" -> should find "Music Arranger"
testRole('Musik Arr', 'Music Arranger', $token, $baseUrl);

// 3. Test "Produksi" -> should find "Production"
testRole('Produksi', 'Production', $token, $baseUrl);

// 4. Test "Art & Set Design" -> should find "Art & Set Properti"
testRole('Art & Set Design', 'Art & Set Properti', $token, $baseUrl);

echo "\n=== DONE ===\n";
