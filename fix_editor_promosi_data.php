<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;

echo "--- Fixing Data for Editor Promosi Works ---\n";

$works = PrEditorPromosiWork::whereNull('pr_editor_work_id')
    ->orWhereNull('pr_promotion_work_id')
    ->get();

echo "Found " . $works->count() . " works with missing foreign keys.\n";

foreach ($works as $work) {
    echo "Processing Work ID: " . $work->id . " (Episode ID: " . $work->pr_episode_id . ")\n";

    $updates = [];

    if (empty($work->pr_editor_work_id)) {
        $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)->first();
        if ($editorWork) {
            $updates['pr_editor_work_id'] = $editorWork->id;
            echo " - Found Editor Work ID: " . $editorWork->id . "\n";
        } else {
            echo " - Editor Work NOT FOUND\n";
        }
    }

    if (empty($work->pr_promotion_work_id)) {
        $promotionWork = PrPromotionWork::where('pr_episode_id', $work->pr_episode_id)->first();
        if ($promotionWork) {
            $updates['pr_promotion_work_id'] = $promotionWork->id;
            echo " - Found Promotion Work ID: " . $promotionWork->id . "\n";
        } else {
            echo " - Promotion Work NOT FOUND\n";
        }
    }

    if (!empty($updates)) {
        $work->update($updates);
        echo " - Updated!\n";
    }
}

echo "Done.\n";
