<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING ROLE FILTERING ===\n";

// Login as manager to get token
$manager = User::where('role', 'Program Manager')->first();
$token = $manager->createToken('TestToken')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8000/api';

function testRole($role, $token, $baseUrl)
{
    echo "\nTesting Role: '{$role}'\n";
    $ch = curl_init();
    // Encode role for URL
    $encodedRole = urlencode($role);
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
    foreach ($data as $user) {
        $userRole = $user['role'];
        $jabatan = $user['employee']['jabatan_saat_ini'] ?? 'N/A';
        echo " - {$user['name']} (Role: {$userRole}, Jabatan: {$jabatan})\n";

        // Simple verification
        if ($userRole === $role || strcasecmp($jabatan, $role) == 0) {
            // Pass
        } else {
            echo "   [WARN] Unexpected result!\n";
        }
    }
}

// 1. Test Direct Role (Producer)
testRole('Producer', $token, $baseUrl);

// 2. Test Jabatan Role (Editor, mapped to Employee role)
testRole('Editor', $token, $baseUrl);

// 3. Test Creative
testRole('Creative', $token, $baseUrl);

// 4. Test Non-existent
testRole('NonExistentRole', $token, $baseUrl);

echo "\n=== DONE ===\n";
