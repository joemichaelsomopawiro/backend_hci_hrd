<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== RE-CREATING PROMOTION WORKS WITH CORRECT ENUM ===\n\n";

// Get a user
$user = \App\Models\User::first();
echo "Using user: {$user->name} (ID: {$user->id})\n\n";

// Get episodes with step 4 completed
$eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
    ->where('status', 'completed')
    ->pluck('episode_id');

echo "Found " . count($eligibleEpisodes) . " episodes with step 4 completed\n\n";

$created = 0;
$skipped = 0;

foreach ($eligibleEpisodes as $episodeId) {
    echo "Processing Episode ID: {$episodeId}... ";

    // Check if already exists
    $exists = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->exists();

    if ($exists) {
        echo "SKIP (already exists)\n";
        $skipped++;
        continue;
    }

    try {
        // Get creative work if available
        $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)
            ->orderBy('created_at', 'desc')
            ->first();

        \App\Models\PrPromotionWork::create([
            'pr_episode_id' => $episodeId,
            'work_type' => 'bts_video',  // Using valid enum value
            'status' => 'planning',
            'created_by' => $user->id,
            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
            'shooting_notes' => 'Created from manual sync - BTS video work'
        ]);

        echo "CREATED ✓\n";
        $created++;
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Created: {$created}\n";
echo "Skipped: {$skipped}\n";
echo "\nPromotion works should now appear in dashboard!\n";
