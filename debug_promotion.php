<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Step 4 Completed Episodes ===\n";
$episodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
    ->where('status', 'completed')
    ->get(['id', 'episode_id', 'workflow_step', 'status', 'completed_at']);

echo "Found " . $episodes->count() . " episodes with step 4 completed:\n";
foreach ($episodes as $ep) {
    echo "  - Episode ID: {$ep->episode_id}, Status: {$ep->status}, Completed: {$ep->completed_at}\n";
}

echo "\n=== Checking Existing Promotion Works ===\n";
$works = \App\Models\PrPromotionWork::all(['id', 'pr_episode_id', 'status', 'created_by']);
echo "Found " . $works->count() . " promotion works:\n";
foreach ($works as $work) {
    echo "  - Work ID: {$work->id}, Episode ID: {$work->pr_episode_id}, Status: {$work->status}\n";
}

echo "\n=== Checking Missing Promotion Works ===\n";
$episodeIds = $episodes->pluck('episode_id');
$existingWorkEpisodeIds = $works->pluck('pr_episode_id');
$missing = $episodeIds->diff($existingWorkEpisodeIds);

echo "Episodes that should have promotion works but don't: " . $missing->count() . "\n";
foreach ($missing as $epId) {
    echo "  - Episode ID: {$epId}\n";
}
