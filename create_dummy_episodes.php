<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrPromotionWork;
use App\Models\PrEpisodeWorkflowProgress;
use Illuminate\Support\Facades\DB;

// 1. Find the program
$programName = 'test 2';
$program = PrProgram::where('name', 'LIKE', "%$programName%")->first();

if (!$program) {
    echo "Program '$programName' not found. Checking for 'Program Test'...\n";
    $program = PrProgram::where('name', 'LIKE', "%Program Test%")->first();
    if (!$program) {
        echo "Program not found at all.\n";
        exit(1);
    }
}

echo "Working on Program: {$program->name} (ID: {$program->id})\n";

// 2. Target episodes 2-5
$episodeNumbers = [2, 3, 4, 5];
$episodes = PrEpisode::where('program_id', $program->id)
    ->whereIn('episode_number', $episodeNumbers)
    ->get();

if ($episodes->isEmpty()) {
    echo "No episodes found for numbers 2-5.\n";
    exit(1);
}

foreach ($episodes as $episode) {
    DB::beginTransaction();
    try {
        echo "Processing Episode {$episode->episode_number} (ID: {$episode->id})...\n";

        // a. Create/Update Creative Work
        $creativeWork = PrCreativeWork::updateOrCreate(
            ['pr_episode_id' => $episode->id],
            [
                'script_content' => "Dummy script for episode {$episode->episode_number}",
                'budget_data' => [
                    'talent' => ['host' => 1000000, 'guest' => 500000],
                    'logistik' => ['location' => 200000, 'konsumsi' => 100000],
                    'operasional' => 50000
                ],
                'status' => 'approved',
                'script_approved' => true,
                'budget_approved' => true,
                'script_approved_by' => $program->producer_id ?? 1, // Fallback to 1 if no producer
                'script_approved_at' => now(),
                'budget_approved_by' => $program->producer_id ?? 1,
                'budget_approved_at' => now(),
                'shooting_schedule' => now()->addDays(7),
                'shooting_location' => "Dummy Location Studio A",
                'created_by' => $program->producer_id ?? 1
            ]
        );

        // b. Create Produksi Work
        PrProduksiWork::firstOrCreate(
            ['pr_episode_id' => $episode->id],
            [
                'pr_creative_work_id' => $creativeWork->id,
                'status' => 'pending'
            ]
        );

        // c. Create Promotion Work
        PrPromotionWork::firstOrCreate(
            ['pr_episode_id' => $episode->id],
            [
                'work_type' => 'bts_video',
                'status' => 'planning',
                'created_by' => $program->producer_id ?? 1,
                'shooting_date' => $creativeWork->shooting_schedule,
                'shooting_notes' => 'Auto-created dummy data'
            ]
        );

        // d. Update Workflow Progress Step 4 (Producer Review)
        PrEpisodeWorkflowProgress::updateOrCreate(
            ['episode_id' => $episode->id, 'workflow_step' => 4],
            [
                'status' => 'completed',
                'completed_at' => now(),
                'remarks' => 'Auto-completed dummy data'
            ]
        );
        
        // Also ensure steps 1, 2, 3 are completed
        for ($step = 1; $step <= 3; $step++) {
            PrEpisodeWorkflowProgress::updateOrCreate(
                ['episode_id' => $episode->id, 'workflow_step' => $step],
                [
                    'status' => 'completed',
                    'completed_at' => now(),
                    'remarks' => 'Auto-completed dummy data'
                ]
            );
        }

        // e. Update Episode Status
        $episode->status = 'production';
        $episode->save();

        DB::commit();
        echo "Successfully advanced Episode {$episode->episode_number} to Step 4.\n";
    } catch (\Exception $e) {
        DB::rollBack();
        echo "Error on Episode {$episode->episode_number}: " . $e->getMessage() . "\n";
    }
}

echo "All done!\n";
