<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CREATING MISSING PROMOTION WORKS ===\n\n";

// Get a Promotion user
$user = \App\Models\User::where('role', 'Promotion')->first();
if (!$user) {
    echo "ERROR: No Promotion user found. Using admin instead.\n";
    $user = \App\Models\User::where('role', 'Admin')->first();
    if (!$user) {
        echo "ERROR: No suitable user found!\n";
        exit(1);
    }
}

echo "Using user: {$user->name} (ID: {$user->id}, Role: {$user->role})\n\n";

// Get episodes with step 4 completed
$eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
    ->where('status', 'completed')
    ->pluck('episode_id');

echo "Found " . count($eligibleEpisodes) . " episodes with step 4 completed\n";
echo "Episode IDs: " . implode(', ', $eligibleEpisodes->toArray()) . "\n\n";

$created = 0;
$skipped = 0;
$errors = 0;

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
            'work_type' => 'general',
            'status' => 'planning',
            'created_by' => $user->id,
            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
            'shooting_notes' => 'Created via manual sync script'
        ]);

        echo "CREATED\n";
        $created++;
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Created: {$created}\n";
echo "Skipped: {$skipped}\n";
echo "Errors: {$errors}\n";
