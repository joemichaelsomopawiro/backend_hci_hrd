<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "--- Broadcasting Work (Latest Rejected) ---" . PHP_EOL;
$bw = \App\Models\BroadcastingWork::where('status', 'rejected')->latest()->first();
if ($bw) {
    echo "ID: {$bw->id}" . PHP_EOL;
    echo "Episode ID: {$bw->episode_id}" . PHP_EOL;
    echo "Editor Work Link: " . ($bw->editor_work_id ?? 'NULL') . PHP_EOL;
    echo "Status: {$bw->status}" . PHP_EOL;
    echo "Work Type: {$bw->work_type}" . PHP_EOL;
    
    echo "\n--- Searching Editor Works for Episode {$bw->episode_id} ---" . PHP_EOL;
    $ews = \App\Models\EditorWork::where('episode_id', $bw->episode_id)->get();
    if ($ews->isEmpty()) {
        echo "No EditorWork found for this episode." . PHP_EOL;
    } else {
        foreach($ews as $ew) {
            echo "ID: {$ew->id}" . PHP_EOL;
            echo "Status: [{$ew->status}]" . PHP_EOL;
            echo "Work Type: [{$ew->work_type}]" . PHP_EOL;
            echo "Created By: {$ew->created_by}" . PHP_EOL;
            echo "QC Feedback: " . ($ew->qc_feedback ?? 'NULL') . PHP_EOL;
        }
    }
} else {
    echo "No rejected BroadcastingWork found." . PHP_EOL;
}

echo "\n--- All Editor Works (Latest 2) ---" . PHP_EOL;
$all_ews = \App\Models\EditorWork::latest()->take(2)->get();
foreach($all_ews as $ew) {
    echo "ID: {$ew->id}, EP: {$ew->episode_id}, STATUS: {$ew->status}, TYPE: {$ew->work_type}" . PHP_EOL;
}
