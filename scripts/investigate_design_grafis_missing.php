<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Design Grafis Works Investigation (Simplified) ===\n\n";

// 1. Check all design grafis works
echo "1. Existing Design Grafis Works:\n";
echo "-----------------------------------\n";
$dgWorks = DB::table('pr_design_grafis_works as dg')
    ->join('pr_episodes as e', 'dg.pr_episode_id', '=', 'e.id')
    ->select('dg.id', 'e.episode_number', 'dg.status', 'dg.created_at')
    ->orderBy('e.episode_number')
    ->get();

foreach ($dgWorks as $work) {
    echo "Episode {$work->episode_number}: ID={$work->id}, Status={$work->status}\n";
}
echo "\n";

// 2. Check which episodes have completed production and promotion
echo "2. Episodes with Completed Production & Promotion:\n";
echo "---------------------------------------------------\n";
$completedEpisodes = DB::table('pr_episodes as e')
    ->join('pr_produksi_works as prod', 'e.id', '=', 'prod.pr_episode_id')
    ->join('pr_promotion_works as promo', 'e.id', '=', 'promo.pr_episode_id')
    ->leftJoin('pr_design_grafis_works as dg', 'e.id', '=', 'dg.pr_episode_id')
    ->select(
        'e.id as episode_id',
        'e.episode_number',
        'prod.id as prod_id',
        'prod.status as production_status',
        'promo.id as promo_id',
        'promo.status as promotion_status',
        'dg.id as design_grafis_id'
    )
    ->where('prod.status', 'completed')
    ->where('promo.status', 'completed')
    ->orderBy('e.episode_number')
    ->get();

foreach ($completedEpisodes as $ep) {
    $hasDG = $ep->design_grafis_id ? "YES (ID: {$ep->design_grafis_id})" : "NO - MISSING!";
    echo "Episode {$ep->episode_number}: Prod={$ep->production_status}, Promo={$ep->promotion_status}, DesignGrafis={$hasDG}\n";
}
echo "\n";

// 3. Check workflow progress table
echo "3. Checking Workflow Progress Table:\n";
echo "------------------------------------\n";
$progressRecords = DB::table('pr_episode_workflow_progresses as p')
    ->join('pr_episodes as e', 'p.episode_id', '=', 'e.id')
    ->select('e.episode_number', 'p.workflow_step', 'p.status')
    ->whereIn('e.episode_number', [5, 6, 7, 8, 9, 53])
    ->orderBy('e.episode_number')
    ->orderBy('p.workflow_step')
    ->get();

$episodes = [];
foreach ($progressRecords as $p) {
    if (!isset($episodes[$p->episode_number])) {
        $episodes[$p->episode_number] = [];
    }
    $episodes[$p->episode_number]["step_{$p->workflow_step}"] = $p->status;
}

foreach ($episodes as $episodeNum => $steps) {
    echo "Episode {$episodeNum}:\n";
    for ($i = 1; $i <= 6; $i++) {
        $stepStatus = $steps["step_{$i}"] ?? 'not_started';
        echo "  Step {$i}: {$stepStatus}\n";
    }
}
echo "\n";

// 4. Missing Design Grafis works
echo "4. Episodes MISSING Design Grafis Works:\n";
echo "----------------------------------------\n";
$missing = DB::table('pr_episodes as e')
    ->join('pr_produksi_works as prod', 'e.id', '=', 'prod.pr_episode_id')
    ->join('pr_promotion_works as promo', 'e.id', '=', 'promo.pr_episode_id')
    ->leftJoin('pr_design_grafis_works as dg', 'e.id', '=', 'dg.pr_episode_id')
    ->whereNull('dg.id')
    ->where('prod.status', 'completed')
    ->where('promo.status', 'completed')
    ->select('e.id', 'e.episode_number', 'prod.id as prod_id', 'promo.id as promo_id')
    ->orderBy('e.episode_number')
    ->get();

if ($missing->count() > 0) {
    echo "Found {$missing->count()} episodes missing Design Grafis works:\n";
    foreach ($missing as $ep) {
        echo "- Episode {$ep->episode_number} (EpisodeID: {$ep->id}, ProdID: {$ep->prod_id}, PromoID: {$ep->promo_id})\n";
    }
    echo "\n\nTo fix, run: php artisan fix:step6\n";
} else {
    echo "All completed episodes have Design Grafis works.\n";
}

echo "\n=== Investigation Complete ===\n";
