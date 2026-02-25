<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrPromotionWork;
use App\Models\PrProduksiWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;

echo "=== Design Grafis Workflow Test ===\n\n";

// 1. Find episodes with Step 5 completed
echo "1. Checking Step 5 completed episodes...\n";
$step5Completed = PrEpisodeWorkflowProgress::where('workflow_step', 5)
    ->where('status', 'completed')
    ->get();

echo "Found " . $step5Completed->count() . " episodes with Step 5 completed\n\n";

// 2. Check if they have both Promotion and Production works completed
foreach ($step5Completed as $progress) {
    $episodeId = $progress->episode_id;
    echo "Episode $episodeId:\n";

    $promotionWork = PrPromotionWork::where('pr_episode_id', $episodeId)
        ->where('status', 'completed')
        ->first();

    $productionWork = PrProduksiWork::where('pr_episode_id', $episodeId)
        ->where('status', 'completed')
        ->first();

    echo "  - Promotion work completed: " . ($promotionWork ? "YES (ID: {$promotionWork->id})" : "NO") . "\n";
    echo "  - Production work completed: " . ($productionWork ? "YES (ID: {$productionWork->id})" : "NO") . "\n";

    // 3. Check if Design Grafis work exists
    $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();
    $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)->first();
    $editorPromosiWork = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();

    echo "  - Design Grafis work: " . ($designGrafisWork ? "EXISTS (ID: {$designGrafisWork->id}, Status: {$designGrafisWork->status})" : "MISSING") . "\n";
    echo "  - Editor work: " . ($editorWork ? "EXISTS (ID: {$editorWork->id}, Status: {$editorWork->status})" : "MISSING") . "\n";
    echo "  - Editor Promosi work: " . ($editorPromosiWork ? "EXISTS (ID: {$editorPromosiWork->id}, Status: {$editorPromosiWork->status})" : "MISSING") . "\n";

    // If both works are completed but Design Grafis work is missing, that's the problem
    if ($promotionWork && $productionWork && !$designGrafisWork) {
        echo "  ⚠️ ERROR: Both Promotion and Production completed but Design Grafis work is MISSING!\n";
    }

    echo "\n";
}

// 4. Summary of all Design Grafis works
echo "\n=== All Design Grafis Works ===\n";
$allDesignGrafisWorks = PrDesignGrafisWork::with('episode')->get();
echo "Total Design Grafis works: " . $allDesignGrafisWorks->count() . "\n";

foreach ($allDesignGrafisWorks as $work) {
    echo "  - Episode {$work->pr_episode_id}: Status={$work->status}, Created={$work->created_at}\n";
}

echo "\n=== Test Complete ===\n";
