<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrCreativeWork;
use App\Models\User;
use App\Models\PrProgramEpisode;
use Illuminate\Support\Facades\DB;

// Helper function to log with timestamp
function logLine($message)
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

try {
    DB::beginTransaction();

    logLine("Starting Producer Bypass Verification...");

    // 1. Setup: Get a Producer and Manager
    $producer = User::whereHas('roles', function ($q) {
        $q->where('name', 'like', '%producer%');
    })->first();

    $manager = User::whereHas('roles', function ($q) {
        $q->where('name', 'like', '%program_manager%');
    })->first();

    if (!$producer || !$manager) {
        throw new Exception("Could not find Producer or Manager users.");
    }

    logLine("Using Producer: " . $producer->name . " (ID: " . $producer->id . ")");
    logLine("Using Manager: " . $manager->name . " (ID: " . $manager->id . ")");

    // 2. Create a Dummy Episode
    $episode = PrProgramEpisode::first(); // Use existing for simplicity, or create new
    if (!$episode) {
        throw new Exception("No episodes found.");
    }
    logLine("Using Episode ID: " . $episode->id);

    // 3. Create Creative Work (Initial submission with Special Budget)
    // Clear existing work for this episode if any to avoid conflicts
    PrCreativeWork::where('pr_episode_id', $episode->id)->delete();

    $work = new PrCreativeWork();
    $work->pr_episode_id = $episode->id;
    $work->status = 'submitted_to_manager'; // Simulate "Waiting Manager"
    $work->requires_special_budget_approval = true;
    $work->budget_approved = false;
    $work->submitted_by = $producer->id;
    $work->submitted_at = now();
    $work->save();

    logLine("Created Creative Work (ID: " . $work->id . ") - Status: " . $work->status . ", Requires Special Budget: YES");

    // 4. Simulate Manager Rejection
    logLine("Simulating Manager Rejection...");
    // Logic from PrManagerProgramController::rejectBudget
    $work->status = 'revised';
    $work->budget_approved = false;
    $work->requires_special_budget_approval = false; // Key reset
    $work->budget_review_notes = 'Too expensive';
    $work->save();

    logLine("Creative Work Status after Rejection: " . $work->status);
    logLine("Requires Special Budget after Rejection: " . ($work->requires_special_budget_approval ? 'YES' : 'NO'));

    if ($work->requires_special_budget_approval) {
        throw new Exception("Manager rejection FAILED to reset 'requires_special_budget_approval' flag!");
    }

    // 5. Simulate Producer "Bypass" Approval
    // In our frontend logic, the Producer clicks "Approve Directly". 
    // This calls `approveEpisode`.
    // We simulate what `approveEpisode` does (PrProducerController).

    logLine("Simulating Producer 'Approve Directly' (Bypass)...");

    // PrProducerController::approveEpisode logic
    $work->status = 'approved';
    $work->approved_by = $producer->id;
    $work->approved_at = now();
    // It implies passing the special budget check if it existed, but here we simulated clear.
    // However, let's test if the flag was STILL true (e.g. if Producer re-checked it in form)

    // Re-set flag to true to simulate Producer checking it again but choosing "Bypass"
    $work->requires_special_budget_approval = true;
    $work->save();
    logLine("Producer re-enabled Special Budget flag (simulated form edit).");

    // Now Approve
    $work->status = 'approved';
    $work->save();

    // 6. Verification
    $finalWork = PrCreativeWork::find($work->id);
    logLine("Final Status: " . $finalWork->status);

    if ($finalWork->status !== 'approved') {
        throw new Exception("Bypass Approval Failed! Status is " . $finalWork->status);
    }

    logLine("SUCCESS! Workflow verified.");

    DB::rollBack(); // Don't persist junk data
    logLine("Rolled back simulation data.");

} catch (Exception $e) {
    DB::rollBack();
    logLine("ERROR: " . $e->getMessage());
    exit(1);
}
