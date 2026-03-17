<?php

use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrManagerDistribusiQcWork;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- Episode 2 Analysis ---\n";
$ep = PrEpisode::find(1119);
if ($ep) {
    echo "Episode: " . $ep->title . " (ID: 1119)\n";
    echo "Program: " . ($ep->program->name ?? 'N/A') . "\n";
    echo "Status: " . $ep->status . "\n";
} else {
    echo "Episode 1119 NOT FOUND\n";
}

echo "\n--- Editor Works for Episode 1119 ---\n";
$editorWorks = PrEditorWork::where('pr_episode_id', 1119)->get();
foreach ($editorWorks as $w) {
    echo "ID: {$w->id} | Status: {$w->status} | WorkType: {$w->work_type}\n";
}

echo "\n--- Manager Distribusi QC Works for Episode 1119 ---\n";
$qcWorks = PrManagerDistribusiQcWork::where('pr_episode_id', 1119)->get();
foreach ($qcWorks as $w) {
    echo "ID: {$w->id} | Status: {$w->status} | CreatedAt: {$w->created_at}\n";
}

echo "\n--- All Manager Distribusi QC Works (Last 20) ---\n";
$allQc = PrManagerDistribusiQcWork::with('episode.program')->orderBy('created_at', 'desc')->limit(20)->get();
foreach ($allQc as $w) {
    echo "ID: {$w->id} | EpID: {$w->pr_episode_id} | Title: " . ($w->episode->title ?? 'N/A') . " | Program: " . ($w->episode->program->name ?? 'N/A') . " | Status: {$w->status}\n";
}
