<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DETAILED ERROR INVESTIGATION ===\n\n";

// Get a user
$user = \App\Models\User::first();
echo "Using user: {$user->name} (ID: {$user->id})\n\n";

// Test with just one episode
$episodeId = 1116; // From earlier debug output

echo "Testing with Episode ID: {$episodeId}\n\n";

try {
    echo "Checking if episode exists...\n";
    $episode = \App\Models\PrEpisode::find($episodeId);
    if ($episode) {
        echo "✓ Episode exists: {$episode->title}\n";
    } else {
        echo "✗ Episode NOT found!\n";
    }

    echo "\nChecking PrPromotionWork model...\n";
    $testWork = new \App\Models\PrPromotionWork();
    echo "✓ Model loaded successfully\n";
    echo "Fillable fields: " . implode(', ', $testWork->getFillable()) . "\n";

    echo "\nAttempting to create promotion work...\n";
    $work = \App\Models\PrPromotionWork::create([
        'pr_episode_id' => $episodeId,
        'work_type' => 'general',
        'status' => 'planning',
        'created_by' => $user->id,
        'shooting_notes' => 'Test creation'
    ]);

    echo "✓ SUCCESS! Created work ID: {$work->id}\n";

} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
