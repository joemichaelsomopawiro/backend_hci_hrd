<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorPromosiWork;

echo "--- Checking Data for Editor Promosi Dashboard ---\n";

// 1. Check for Episodes that SHOULD appear
// Condition: Editor Work (pending_qc OR completed) AND Promotion Work (completed)

$candidates = PrEpisode::with(['editorWork', 'promotionWork', 'editorPromosiWork'])
    ->get()
    ->filter(function ($ep) {
        $editorReady = $ep->editorWork && in_array($ep->editorWork->status, ['pending_qc', 'completed']);
        $promotionReady = $ep->promotionWork && $ep->promotionWork->status === 'completed';
        return $editorReady && $promotionReady;
    });

echo "Found " . $candidates->count() . " episodes matching the criteria.\n";

if ($candidates->count() > 0) {
    foreach ($candidates as $ep) {
        echo "Example: Episode " . $ep->episode_number . " (" . $ep->title . ")\n";
        echo " - Editor Status: " . ($ep->editorWork ? $ep->editorWork->status : 'N/A') . "\n";
        echo " - Promotion Status: " . ($ep->promotionWork ? $ep->promotionWork->status : 'N/A') . "\n";

        $epWork = $ep->editorPromosiWork;
        if ($epWork) {
            echo " - Editor Promosi Work ID: " . $epWork->id . "\n";
            echo " - Status: " . $epWork->status . "\n";
            echo " - pr_editor_work_id: " . ($epWork->pr_editor_work_id ?? 'NULL') . "\n";
            echo " - pr_promotion_work_id: " . ($epWork->pr_promotion_work_id ?? 'NULL') . "\n";
            echo " - Loaded editorWork: " . ($epWork->editorWork ? 'YES' : 'NO') . "\n";
            echo " - Loaded promotionWork: " . ($epWork->promotionWork ? 'YES' : 'NO') . "\n";
        } else {
            echo " - Editor Promosi Work: DOES NOT EXIST (This is the problem!)\n";
        }
    }
} else {
    echo "No episodes currently meet the criteria.\n";

    // Check closest candidates (Editor Only)
    $editorOnly = PrEpisode::whereHas('editorWork', function ($q) {
        $q->whereIn('status', ['pending_qc', 'completed']);
    })->count();
    echo "Episodes with Editor Ready: $editorOnly\n";

    // Check closest candidates (Promotion Only)
    $promotionOnly = PrEpisode::whereHas('promotionWork', function ($q) {
        $q->where('status', 'completed');
    })->count();
    echo "Episodes with Promotion Ready: $promotionOnly\n";
}
