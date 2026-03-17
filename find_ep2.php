<?php
use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Search for EP 2 in PROGRAM TEST (ID 36)
$ep = PrEpisode::where('pr_program_id', 36)
    ->where(function($q) {
        $q->where('title', 'like', '%EP 2%')
          ->orWhere('title', 'like', '%Episode 2%');
    })->first();

if (!$ep) {
    // If not found, list all episodes for program 36 to see what's there
    echo "EPISODE_NOT_FOUND_BY_SEARCH\n";
    $allEps = PrEpisode::where('pr_program_id', 36)->get();
    foreach ($allEps as $e) {
        echo "EpID:{$e->id}|Title:{$e->title}|Status:{$e->status}\n";
    }
} else {
    echo "FOUND_EPISODE: ID:{$ep->id}|Title:{$ep->title}|Status:{$ep->status}\n";
    
    $editorWork = PrEditorWork::where('pr_episode_id', $ep->id)->get();
    echo "EDITOR_WORKS_COUNT: " . $editorWork->count() . "\n";
    foreach ($editorWork as $w) {
        echo "EW_ID:{$w->id}|WorkType:{$w->work_type}|Status:{$w->status}\n";
    }
    
    $qcWork = PrManagerDistribusiQcWork::where('pr_episode_id', $ep->id)->get();
    echo "QC_WORKS_COUNT: " . $qcWork->count() . "\n";
    foreach ($qcWork as $w) {
        echo "QC_ID:{$w->id}|Status:{$w->status}\n";
    }
}
