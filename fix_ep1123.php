<?php
require __DIR__ . '/vendor/autoload.php';
chdir(__DIR__);
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$epId = 1123;
echo "Fixing Episode ID $epId (Episode 10)\n";

$ep = \App\Models\PrEpisode::find($epId);
if (!$ep) {
    die("Episode not found\n");
}

// 1. Ensure PrPromotionWork exists
$promo = \App\Models\PrPromotionWork::where('pr_episode_id', $epId)->first();
if (!$promo) {
    echo "Creating missing PrPromotionWork...\n";
    $cw = \App\Models\PrCreativeWork::where('pr_episode_id', $epId)->latest()->first();
    $promo = \App\Models\PrPromotionWork::create([
        'pr_episode_id' => $epId,
        'work_type' => 'bts_video',
        'status' => 'planning',
        'created_by' => $cw ? $cw->created_by : 1, // Default to admin if nu user
        'shooting_date' => $cw ? $cw->shooting_schedule : null,
        'shooting_notes' => 'Auto-created via fix script'
    ]);
    echo "Created Promotion Work ID: {$promo->id}\n";
} else {
    echo "PrPromotionWork exists (ID: {$promo->id})\n";
}

// 2. Fix Step 4 Status
$step4 = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $epId)
    ->where('workflow_step', 4)
    ->first();

if ($step4 && $step4->status !== 'completed') {
    echo "Marking Step 4 as completed...\n";
    $step4->update([
        'status' => 'completed',
        'completed_at' => now(),
        'notes' => 'Fixed via script'
    ]);
} else {
    echo "Step 4 is already " . ($step4 ? $step4->status : 'missing') . "\n";
}

// 3. Check/Fix Step 5 (since Production is completed)
// If both Promotion and Production are completed, Step 5 should be completed.
$prod = \App\Models\PrProduksiWork::where('pr_episode_id', $epId)->first();

if ($prod && $prod->status === 'completed' && $promo->status === 'completed') {
    // Check Step 5
    $step5 = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $epId)
        ->where('workflow_step', 5)
        ->first();

    if ($step5 && $step5->status !== 'completed') {
        echo "Marking Step 5 as completed...\n";
        $step5->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}

echo "Done.\n";
