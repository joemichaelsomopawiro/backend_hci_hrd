<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking ALL Episodes That Passed Step 5 ===\n\n";

// Find ALL episodes where both production and promotion are completed
$allCompleted = DB::table('pr_episodes as e')
    ->join('pr_produksi_works as prod', 'e.id', '=', 'prod.pr_episode_id')
    ->join('pr_promotion_works as promo', 'e.id', '=', 'promo.pr_episode_id')
    ->leftJoin('pr_design_grafis_works as dg', 'e.id', '=', 'dg.pr_episode_id')
    ->leftJoin('pr_editor_works as ed', 'e.id', '=', 'ed.pr_episode_id')
    ->leftJoin('pr_editor_promosi_works as ep', 'e.id', '=', 'ep.pr_episode_id')
    ->where('prod.status', 'completed')
    ->where('promo.status', 'completed')
    ->select(
        'e.id as episode_id',
        'e.episode_number',
        'prod.status as prod_status',
        'promo.status as promo_status',
        'dg.id as has_design_grafis',
        'ed.id as has_editor',
        'ep.id as has_editor_promosi'
    )
    ->orderBy('e.episode_number')
    ->get();

echo "Found {$allCompleted->count()} episodes with completed Production & Promotion:\n\n";

$missingDG = [];
$missingEditor = [];
$missingEditorPromosi = [];

foreach ($allCompleted as $ep) {
    $dgStatus = $ep->has_design_grafis ? "✓" : "✗ MISSING";
    $edStatus = $ep->has_editor ? "✓" : "✗ MISSING";
    $epStatus = $ep->has_editor_promosi ? "✓" : "✗ MISSING";

    echo "Episode {$ep->episode_number}:\n";
    echo "  - Design Grafis: {$dgStatus}\n";
    echo "  - Editor: {$edStatus}\n";
    echo "  - Editor Promosi: {$epStatus}\n";

    if (!$ep->has_design_grafis)
        $missingDG[] = $ep;
    if (!$ep->has_editor)
        $missingEditor[] = $ep;
    if (!$ep->has_editor_promosi)
        $missingEditorPromosi[] = $ep;
}

echo "\n=== Summary ===\n";
echo "Missing Design Grafis works: " . count($missingDG) . " episodes\n";
echo "Missing Editor works: " . count($missingEditor) . " episodes\n";
echo "Missing Editor Promosi works: " . count($missingEditorPromosi) . " episodes\n";

if (count($missingDG) > 0 || count($missingEditor) > 0 || count($missingEditorPromosi) > 0) {
    echo "\n✅ Run the create_missing_design_grafis_works.php script to fix this!\n";
} else {
    echo "\n✅ All episodes have their Step 6 works created!\n";
}
