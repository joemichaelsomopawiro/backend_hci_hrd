<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;

echo "Repairing Editor Promosi links...\n";

$works = PrEditorPromosiWork::whereNull('pr_editor_work_id')->get();
$fixed = 0;

foreach ($works as $work) {
    $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)->first();
    if ($editorWork) {
        $work->update(['pr_editor_work_id' => $editorWork->id]);
        echo "Linked Work ID {$work->id} to Editor Work ID {$editorWork->id} for Episode ID {$work->pr_episode_id}\n";
        $fixed++;
    } else {
        echo "WARNING: No Editor Work found for Episode ID {$work->pr_episode_id} (Work ID {$work->id})\n";
    }
}

echo "Finished. Fixed {$fixed} records.\n";
