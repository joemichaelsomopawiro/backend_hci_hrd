<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Deadline;
use App\Models\CreativeWork;
use App\Services\TaskReassignmentService;
use Illuminate\Support\Facades\DB;

echo "--- FINAL QUALITY CHECK: REASSIGNMENT SYNC ---\n";

// 1. Pick a target (Episode 3374, role 'kreatif')
$deadline = Deadline::where('episode_id', 3374)->where('role', 'kreatif')->first();
if (!$deadline) { echo "Deadline not found\n"; exit; }

$oldUserId = $deadline->assigned_user_id;
$testUserId = 1; // Super Admin for testing

echo "Target Deadline ID: {$deadline->id}\n";
echo "Current Assigned User: {$oldUserId}\n";
echo "CreativeWork User Before: " . (CreativeWork::where('episode_id', 3374)->first()->created_by ?? 'NONE') . "\n";

// 2. Perform Reassignment simulated
echo "\nPerforming Reassignment to User ID {$testUserId}...\n";
$result = TaskReassignmentService::reassignTask(
    'deadline',
    $deadline->id,
    $testUserId, // New user
    14,          // Reassigned by (Admin)
    'Final QA Verification'
);

if ($result['success']) {
    echo "Reassignment logic SUCCESS!\n";
} else {
    echo "Reassignment logic FAILED: " . $result['error'] . "\n";
    exit;
}

// 3. Verify Cross-Sync
$newDeadline = Deadline::find($deadline->id);
$newWork = CreativeWork::where('episode_id', 3374)->first();

echo "\n--- VERIFICATION ---\n";
echo "Deadline Assigned To: " . ($newDeadline->assigned_user_id) . " (Expected: {$testUserId})\n";
echo "CreativeWork Created By: " . ($newWork->created_by) . " (Expected: {$testUserId})\n";

if ($newDeadline->assigned_user_id == $testUserId && $newWork->created_by == $testUserId) {
    echo "\nRESULT: 100% FIKS! Parallel Sync confirmed.\n";
} else {
    echo "\nRESULT: Sync failed check.\n";
}

// 4. CLEANUP (Revert back to original user or Team HCI user)
$newWork->update(['created_by' => $oldUserId]);
$newDeadline->update(['assigned_user_id' => $oldUserId]);
echo "\nCleaned up and reverted to user {$oldUserId}.\n";
