<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EditorWork;
use App\Models\BroadcastingWork;

echo "--- Music Editor Works Check ---\n";
// Music logic uses EditorWork model (PrEditorWork is for Regular Program)
$editorWorks = EditorWork::select('id', 'status', 'episode_id', 'created_at')->get();
echo "Total Music Editor Works: " . $editorWorks->count() . "\n";

$pendingQC = $editorWorks->where('status', 'pending_qc');
echo "Music Editor Works Pending QC (Awaiting DM Review): " . $pendingQC->count() . "\n";

foreach ($pendingQC as $ew) {
    echo "- Music Editor Work ID: {$ew->id}, Episode ID: {$ew->episode_id}, Status: {$ew->status}\n";
    
    // Check if a broadcasting work exists for this episode
    $bw = BroadcastingWork::where('episode_id', $ew->episode_id)->first();
    if ($bw) {
        echo "  - Corresponding BroadcastingWork exists (ID: {$bw->id}, Status: {$bw->status})\n";
    } else {
        echo "  - MISSING: No BroadcastingWork record found for this episode!\n";
    }
}
