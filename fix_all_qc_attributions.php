<?php

use App\Models\QualityControlWork;
use App\Models\DesignGrafisWork;
use App\Models\PromotionWork;
use App\Models\EditorWork;
use App\Models\User;

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "Starting Global QC Attribution Cleanup...\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------\n";

$stats = [
    'design' => 0,
    'promotion' => 0,
    'video' => 0,
    'already_correct' => 0,
    'not_found' => 0
];

// 1. Process all QualityControlWork records
$qcWorks = QualityControlWork::with(['episode'])->get();

foreach ($qcWorks as $qcWork) {
    echo "Processing QC Record #{$qcWork->id} (Type: {$qcWork->qc_type}, Episode: {$qcWork->episode_id})...\n";
    
    $correctWorkerId = null;
    $sourceType = "";

    // CATEGORY: DESIGN
    if (in_array($qcWork->qc_type, ['thumbnail_yt', 'thumbnail_bts', 'graphics_ig', 'graphics_facebook', 'poster'])) {
        $sourceType = "Design";
        // Find Design Work for this episode
        // Map QC type to Design Work work_type if necessary
        $workTypeMap = [
            'thumbnail_yt' => 'thumbnail_youtube',
            'thumbnail_bts' => 'thumbnail_bts'
        ];
        $targetWorkType = $workTypeMap[$qcWork->qc_type] ?? $qcWork->qc_type;
        
        $work = DesignGrafisWork::where('episode_id', $qcWork->episode_id)
            ->where('work_type', $targetWorkType)
            ->first();
            
        if ($work) {
            $correctWorkerId = $work->assigned_to ?: $work->created_by;
        }
    } 
    // CATEGORY: PROMOSI
    else if (in_array($qcWork->qc_type, ['bts_video', 'advertisement_tv', 'highlight_ig', 'highlight_tv', 'tiktok', 'reels_facebook', 'promotion'])) {
        $sourceType = "Promotion";
        $work = PromotionWork::where('episode_id', $qcWork->episode_id)
            ->where('work_type', $qcWork->qc_type)
            ->first();
            
        if ($work) {
            $correctWorkerId = $work->assigned_to ?: $work->created_by;
        }
    }
    // CATEGORY: MAIN VIDEO
    else if ($qcWork->qc_type === 'main_episode') {
        $sourceType = "Video";
        $work = EditorWork::where('episode_id', $qcWork->episode_id)->first();
        if ($work) {
            $correctWorkerId = $work->assigned_to ?: $work->created_by;
        }
    }

    // APPLY UPDATE
    if ($correctWorkerId) {
        if ($qcWork->created_by != $correctWorkerId) {
            $oldUser = User::find($qcWork->created_by);
            $newUser = User::find($correctWorkerId);
            $oldName = $oldUser ? $oldUser->name : "Unknown (ID: {$qcWork->created_by})";
            $newName = $newUser ? $newUser->name : "Unknown (ID: {$correctWorkerId})";
            
            echo "  [FIX] Re-attributing {$sourceType} QC #{$qcWork->id}: '{$oldName}' -> '{$newName}'\n";
            $qcWork->update(['created_by' => $correctWorkerId]);
            
            if ($sourceType === "Design") $stats['design']++;
            if ($sourceType === "Promotion") $stats['promotion']++;
            if ($sourceType === "Video") $stats['video']++;
        } else {
            echo "  [OK] Attribution already correct.\n";
            $stats['already_correct']++;
        }
    } else {
        echo "  [SKIP] No source work found for this QC record.\n";
        $stats['not_found']++;
    }
}

echo "--------------------------------------------------\n";
echo "Cleanup Summary:\n";
echo "- Design records fixed: {$stats['design']}\n";
echo "- Promotion records fixed: {$stats['promotion']}\n";
echo "- Video records fixed: {$stats['video']}\n";
echo "- Already correct: {$stats['already_correct']}\n";
echo "- Source not found: {$stats['not_found']}\n";
echo "--------------------------------------------------\n";
echo "SUCCESS: Global Cleanup Complete.\n";
