<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrEpisode;
use App\Models\PrProgram;
use App\Models\User;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorPromosiWork;
use App\Services\PrWorkflowService;
use App\Models\PrEpisodeWorkflowProgress;

echo "Starting verification for Step 6 Logic...\n";

// 1. Create a dummy user
$user = User::first(); // Just use first user
if (!$user) {
    die("No user found. Please run migration/seed.\n");
}

// 2. Create a dummy program and episode
$program = PrProgram::first();
if (!$program) {
    // create simple program if none
    $program = PrProgram::create([
        'name' => 'Test Program',
        'desc' => 'Test',
        'status' => 'active',
        'created_by' => $user->id,
        'manager_program_id' => $user->id
    ]);
}

$episode = PrEpisode::create([
    'pr_program_id' => $program->id,
    'episode_number' => 9999, // Distinct number
    'title' => 'Test Episode Step 6 Verification',
    'status' => 'in_progress',
    'created_by' => $user->id
]);

echo "Created Episode ID: " . $episode->id . "\n";

// 3. Initialize Workflow
$service = app(PrWorkflowService::class);
$service->initializeWorkflow($episode);

// 4. Create sub-works with required statuses
// Editor: pending_qc
PrEditorWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'pending_qc',
    'pr_production_work_id' => 1, // Dummy
    'assigned_to' => $user->id
]);
echo "Created Editor Work (pending_qc)\n";

// Promotion: completed
PrPromotionWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'completed',
    'created_by' => $user->id
]);
echo "Created Promotion Work (completed)\n";

// Editor Promosi: pending_qc
PrEditorPromosiWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'pending_qc',
    'assigned_to' => $user->id
]);
echo "Created Editor Promosi Work (pending_qc)\n";

// 5. Check Step 6 status BEFORE
$step6 = PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
    ->where('workflow_step', 6)
    ->first();
echo "Step 6 Status BEFORE: " . $step6->status . "\n";

// 6. Call getWorkflowVisualization to trigger logic
$data = $service->getWorkflowVisualization($episode->id);

// 7. Check Step 6 status AFTER
$step6->refresh();
echo "Step 6 Status AFTER: " . $step6->status . "\n";

if ($step6->status === 'completed') {
    echo "SUCCESS: Step 6 was auto-completed.\n";
} else {
    echo "FAILED: Step 6 was NOT auto-completed.\n";
}

// Cleanup (optional, but requested episode_number 9999)
// $episode->delete(); // Keep it for inspection if needed logic allows soft delete
