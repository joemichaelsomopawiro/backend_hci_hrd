<?php

use App\Models\PrEpisode;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrProduksiWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking for episodes with completed Step 5 but missing Step 6 works...\n";

// Find episodes where Step 5 is completed
$step5Progress = PrEpisodeWorkflowProgress::where('workflow_step', 5)
    ->where('status', 'completed')
    ->get();

$count = 0;

foreach ($step5Progress as $progress) {
    $episodeId = $progress->episode_id;
    echo "Checking Episode ID: $episodeId\n";

    // Check if works exist
    $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)->first();
    $editorPromosiWork = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
    $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

    if (!$editorWork || !$editorPromosiWork || !$designGrafisWork) {
        echo "Found missing works for Episode ID: $episodeId. Creating them...\n";

        $productionWork = PrProduksiWork::where('pr_episode_id', $episodeId)->first();
        $promotionWork = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

        if ($productionWork && $promotionWork) {
            // 1. Create Editor work
            if (!$editorWork) {
                PrEditorWork::create([
                    'pr_episode_id' => $episodeId,
                    'pr_production_work_id' => $productionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending',
                    'files_complete' => false
                ]);
                echo "- Created Editor Work\n";
            }

            // 2. Create Editor Promosi work
            if (!$editorPromosiWork) {
                PrEditorPromosiWork::create([
                    'pr_episode_id' => $episodeId,
                    'pr_editor_work_id' => null, // Will be linked when Editor work is created/updated
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]);
                echo "- Created Editor Promosi Work\n";
            }

            // 3. Create Design Grafis work
            if (!$designGrafisWork) {
                PrDesignGrafisWork::create([
                    'pr_episode_id' => $episodeId,
                    'pr_production_work_id' => $productionWork->id,
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]);
                echo "- Created Design Grafis Work\n";
            }

            $count++;
        } else {
            echo "Skipping Episode ID: $episodeId because Production or Promotion work is missing.\n";
        }
    } else {
        echo "All Step 6 works exist for Episode ID: $episodeId.\n";
    }
}

echo "Fixed $count episodes.\n";
