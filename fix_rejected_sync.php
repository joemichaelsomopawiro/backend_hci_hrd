<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Running Data Fix Sync..." . PHP_EOL;

// 1. Find all rejected broadcasting works
$rejectedBws = \App\Models\BroadcastingWork::where('status', 'rejected')->get();
echo "Found " . $rejectedBws->count() . " rejected broadcasting works." . PHP_EOL;

foreach($rejectedBws as $bw) {
    echo "Processing BW ID: {$bw->id} (EP: {$bw->episode_id})..." . PHP_EOL;
    
    // Find corresponding editor work
    $ew = null;
    if ($bw->editor_work_id) {
        $ew = \App\Models\EditorWork::find($bw->editor_work_id);
    }
    
    if (!$ew) {
        $ew = \App\Models\EditorWork::where('episode_id', $bw->episode_id)
            ->whereIn('status', ['completed', 'submitted'])
            ->latest()
            ->first();
    }
    
    if ($ew) {
        echo "Found EditorWork ID: {$ew->id} with status [{$ew->status}]. Updating to rejected..." . PHP_EOL;
        $ew->update([
            'status' => 'rejected',
            'qc_feedback' => "[REJECTION SYNC FIX] " . ($bw->rejection_notes ?? 'Pekerjaan ditolak oleh QC')
        ]);
        echo "SUCCESS: Updated ID {$ew->id}" . PHP_EOL;
    } else {
        echo "No matching EditorWork found for EP {$bw->episode_id}" . PHP_EOL;
    }
}

echo "\n--- Final Statistics Check ---" . PHP_EOL;
$total = \App\Models\EditorWork::count();
$draft = \App\Models\EditorWork::whereIn('status', ['draft', 'pending', 'rejected'])->count();
$submitted = \App\Models\EditorWork::where('status', 'submitted')->count();
$approved = \App\Models\EditorWork::whereIn('status', ['approved', 'completed'])->count();

echo "Total: $total, Draft/Revision: $draft, Submitted/QC: $submitted, Approved/Final: $approved" . PHP_EOL;
