<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Auto-Sync Logic Manually ===\n\n";

// Simulate user (Promotion role)
$user = \App\Models\User::where('role', 'Promotion')->first();

if (!$user) {
    echo "ERROR: No user with role 'Promotion' found!\n";
    echo "Available roles in database:\n";
    $roles = \App\Models\User::distinct()->pluck('role');
    foreach ($roles as $role) {
        echo "  - $role\n";
    }
    exit(1);
}

echo "Using user: {$user->name} (Role: {$user->role}, ID: {$user->id})\n\n";

// Run the auto-sync logic
echo "Fetching eligible episodes (step 4 completed)...\n";
$eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
    ->where('status', 'completed')
    ->pluck('episode_id');

echo "Found " . $eligibleEpisodes->count() . " eligible episodes: " . $eligibleEpisodes->implode(', ') . "\n\n";

foreach ($eligibleEpisodes as $episodeId) {
    echo "Processing Episode ID: {$episodeId}\n";

    try {
        // Check if promotion work exists
        $exists = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->exists();

        if ($exists) {
            echo "  ✓ Promotion work already exists, skipping\n\n";
            continue;
        }

        echo "  - No promotion work found, creating...\n";

        // Get creative work to copy shooting date if possible
        $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($creativeWork) {
            echo "  - Found creative work ID: {$creativeWork->id}\n";
        } else {
            echo "  - No creative work found\n";
        }

        $promotionWork = \App\Models\PrPromotionWork::create([
            'pr_episode_id' => $episodeId,
            'work_type' => 'general',
            'status' => 'planning',
            'created_by' => $user->id,
            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
            'shooting_notes' => 'Auto-created from dashboard sync'
        ]);

        echo "  ✓ Successfully created promotion work ID: {$promotionWork->id}\n\n";

    } catch (\Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n\n";
    }
}

echo "\n=== Verification ===\n";
$works = \App\Models\PrPromotionWork::whereIn('pr_episode_id', $eligibleEpisodes)->get();
echo "Total promotion works for eligible episodes: " . $works->count() . "\n";
foreach ($works as $work) {
    echo "  - Episode ID: {$work->pr_episode_id}, Status: {$work->status}, Work ID: {$work->id}\n";
}
