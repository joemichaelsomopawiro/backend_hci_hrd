<?php

use App\Models\PrEditorWork;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "Checking PrEditorWorks with 'revision_requested' status...\n";

$works = PrEditorWork::where('status', 'revision_requested')->get();

if ($works->isEmpty()) {
    echo "No works found with 'revision_requested' status.\n";
} else {
    foreach ($works as $work) {
        echo "ID: {$work->id}, Episode ID: {$work->pr_episode_id}\n";
        echo "Status: {$work->status}\n";
        echo "File Notes: " . ($work->file_notes ? "'{$work->file_notes}'" : "NULL/Empty") . "\n";
        echo "--------------------------------------------------\n";
    }
}
