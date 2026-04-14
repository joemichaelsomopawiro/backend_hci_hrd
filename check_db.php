<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check episode relations - which episodes have existing data
$episodes = \App\Models\PrEpisode::whereNotNull('program_id')
    ->whereHas('program')  // Only episodes with existing programs
    ->take(10)->get();

echo '=== PR_EPISODES WITH PROGRAMS ===' . PHP_EOL;
echo 'Found: ' . $episodes->count() . PHP_EOL;
foreach ($episodes as $ep) {
    echo 'ep_id=' . $ep->id . ' | program=' . ($ep->program ? $ep->program->name : 'NULL') . ' | ep#' . $ep->episode_number . ' | air=' . $ep->air_date . ' | status=' . $ep->status . PHP_EOL;
}

// Check PrEpisode model for the episode relationship name on creative work
echo PHP_EOL . '=== CREATIVE WORKS WITH EPISODES ===' . PHP_EOL;
$cws = \App\Models\PrCreativeWork::with('episode.program')->take(5)->get();
foreach ($cws as $cw) {
    $epId = $cw->pr_episode_id;
    $ep = $cw->episode;
    echo 'cw_id=' . $cw->id . ' | pr_episode_id=' . $epId . ' | user=' . $cw->created_by . ' | status=' . $cw->status;
    echo ' | reviewed_at=' . ($cw->reviewed_at ?? 'NULL');
    echo ' | orig_assigned=' . ($cw->originally_assigned_to ?? 'NULL');
    if ($ep) {
        echo ' | ep#' . $ep->episode_number . ' | program=' . ($ep->program ? $ep->program->name : 'NULL');
    }
    echo PHP_EOL;
}

// Check PrEditorWork relations
echo PHP_EOL . '=== EDITOR WORKS ===' . PHP_EOL;
$ews = \App\Models\PrEditorWork::take(5)->get();
echo 'Total editor works: ' . \App\Models\PrEditorWork::count() . PHP_EOL;
foreach ($ews as $ew) {
    echo 'ew_id=' . $ew->id . ' | pr_episode_id=' . $ew->pr_episode_id . ' | orig_assigned=' . $ew->originally_assigned_to . ' | status=' . $ew->status . PHP_EOL;
}

// Check music-related tables
echo PHP_EOL . '=== MUSIC TABLES ===' . PHP_EOL;
$musicTables = ['music_programs', 'music_episodes', 'music_arranger_works', 'sound_engineer_works'];
foreach ($musicTables as $t) {
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
            $count = \Illuminate\Support\Facades\DB::table($t)->count();
            echo $t . ': ' . $count . ' rows' . PHP_EOL;
            if ($count > 0) {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing($t);
                echo '  columns: ' . implode(', ', $cols) . PHP_EOL;
            }
        } else {
            echo $t . ': TABLE NOT FOUND' . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo $t . ': ERROR - ' . $e->getMessage() . PHP_EOL;
    }
}

// KpiQualityScore model check
echo PHP_EOL . '=== KPI_QUALITY_SCORES TABLE ===' . PHP_EOL;
try {
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('kpi_quality_scores');
    echo implode(', ', $cols) . PHP_EOL;
} catch (\Exception $e) {
    echo 'TABLE NOT FOUND: ' . $e->getMessage() . PHP_EOL;
}

// Check what creative work 'episode' relation looks like in PrCreativeWork model
echo PHP_EOL . '=== PrCreativeWork episode relation ===' . PHP_EOL;
$ref = new ReflectionClass(\App\Models\PrCreativeWork::class);
if ($ref->hasMethod('episode')) {
    $method = $ref->getMethod('episode');
    $start = $method->getStartLine();
    $end = $method->getEndLine();
    $file = file($method->getFileName());
    for ($i = $start - 1; $i < $end; $i++) {
        echo trim($file[$i]) . PHP_EOL;
    }
}

echo PHP_EOL . "DONE" . PHP_EOL;
