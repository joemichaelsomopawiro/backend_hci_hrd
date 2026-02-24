<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PrEpisode;
use Illuminate\Support\Facades\Http;

// Helper function to log with timestamp
function logLine($message)
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

try {
    logLine("Starting Manager Crew Assignment Verification...");

    // 1. Get a Program Manager
    // Try exact match first
    $manager = User::where('role', 'Program Manager')->first();

    if (!$manager) {
        // Try loose match
        $manager = User::where('role', 'like', '%Manager%')->where('role', 'like', '%Program%')->first();
    }

    if (!$manager) {
        throw new Exception("Could not find a Program Manager user.");
    }

    logLine("Using Manager: " . $manager->name . " (ID: " . $manager->id . ")");

    // 2. Get an Episode
    $episode = PrEpisode::first();
    if (!$episode) {
        throw new Exception("No episodes found.");
    }
    logLine("Using Episode ID: " . $episode->id);

    // 3. Generate Token
    $token = $manager->createToken('test-token')->plainTextToken;
    logLine("Generated test token.");

    // 4. Test ADD Crew (POST)
    $baseUrl = 'http://127.0.0.1:8000/api/program-regular/manager-program';
    $url = "{$baseUrl}/episodes/{$episode->id}/crews";

    // Use the manager themselves as the crew member for simplicity, or find another user
    $crewUser = User::where('id', '!=', $manager->id)->first();
    if (!$crewUser)
        $crewUser = $manager;

    logLine("Attempting to add user ID {$crewUser->id} as 'shooting_team'...");

    $response = Http::withToken($token)->post($url, [
        'user_id' => $crewUser->id,
        'role' => 'shooting_team'
    ]);

    logLine("POST Response Status: " . $response->status());
    logLine("POST Response Body: " . $response->body());

    $crewId = null;

    if ($response->successful()) {
        $data = $response->json();
        $crewId = $data['data']['id'] ?? null;
        logLine("SUCCESS: Crew added. ID: " . $crewId);
    } elseif ($response->status() === 400 && strpos($response->body(), 'already assigned') !== false) {
        logLine("User already assigned. Fetching existing assignment to test delete...");
        // fetch existing to get ID
        $existing = \App\Models\PrEpisodeCrew::where('episode_id', $episode->id)
            ->where('user_id', $crewUser->id)
            ->where('role', 'shooting_team')
            ->first();
        if ($existing) {
            $crewId = $existing->id;
            logLine("Found existing crew ID: " . $crewId);
        }
    } else {
        throw new Exception("Failed to add crew. " . $response->body());
    }

    // 5. Test REMOVE Crew (DELETE)
    if ($crewId) {
        $deleteUrl = "{$baseUrl}/episodes/{$episode->id}/crews/{$crewId}";
        logLine("Attempting to remove crew ID {$crewId}...");

        $deleteResponse = Http::withToken($token)->delete($deleteUrl);

        logLine("DELETE Response Status: " . $deleteResponse->status());

        if ($deleteResponse->successful()) {
            logLine("SUCCESS: Crew removed.");
        } else {
            throw new Exception("Failed to remove crew. " . $deleteResponse->body());
        }
    }

    // Cleanup Token
    $manager->tokens()->where('name', 'test-token')->delete();
    logLine("Cleaned up test token.");

} catch (Exception $e) {
    logLine("ERROR: " . $e->getMessage());
    exit(1);
}
