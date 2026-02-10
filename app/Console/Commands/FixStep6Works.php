<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixStep6Works extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:step6';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix missing Step 6 works for episodes that completed Step 5';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Checking for episodes with completed Step 5 but missing Step 6 works...");

        // Find episodes where Step 5 is completed
        $step5Progress = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 5)
            ->where('status', 'completed')
            ->get();

        $count = 0;

        foreach ($step5Progress as $progress) {
            $episodeId = $progress->episode_id;
            $this->info("Checking Episode ID: $episodeId");

            // Check if works exist
            $editorWork = \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)->first();
            $editorPromosiWork = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
            $designGrafisWork = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

            if (!$editorWork || !$editorPromosiWork || !$designGrafisWork) {
                $this->info("Found missing works for Episode ID: $episodeId. Creating them...");

                $productionWork = \App\Models\PrProduksiWork::where('pr_episode_id', $episodeId)->first();
                $promotionWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                if ($productionWork && $promotionWork) {
                    // 1. Create Editor work
                    if (!$editorWork) {
                        \App\Models\PrEditorWork::create([
                            'pr_episode_id' => $episodeId,
                            'pr_production_work_id' => $productionWork->id,
                            'assigned_to' => null,
                            'status' => 'pending',
                            'files_complete' => false
                        ]);
                        $this->info("- Created Editor Work");
                    }

                    // 2. Create Editor Promosi work
                    if (!$editorPromosiWork) {
                        \App\Models\PrEditorPromosiWork::create([
                            'pr_episode_id' => $episodeId,
                            'pr_editor_work_id' => null, // Will be linked when Editor work is created/updated
                            'pr_promotion_work_id' => $promotionWork->id,
                            'assigned_to' => null,
                            'status' => 'pending'
                        ]);
                        $this->info("- Created Editor Promosi Work");
                    }

                    // 3. Create Design Grafis work
                    if (!$designGrafisWork) {
                        \App\Models\PrDesignGrafisWork::create([
                            'pr_episode_id' => $episodeId,
                            'pr_production_work_id' => $productionWork->id,
                            'pr_promotion_work_id' => $promotionWork->id,
                            'assigned_to' => null,
                            'status' => 'pending'
                        ]);
                        $this->info("- Created Design Grafis Work");
                    }

                    $count++;
                } else {
                    $this->warn("Skipping Episode ID: $episodeId because Production or Promotion work is missing.");
                }
            } else {
                $this->info("All Step 6 works exist for Episode ID: $episodeId.");
            }
        }

        $this->info("Fixed $count episodes.");
    }
}
