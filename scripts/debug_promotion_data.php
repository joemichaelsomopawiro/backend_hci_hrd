<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrPromotionWork;
use App\Models\PrCreativeWork;
use App\Models\PrEpisode;

echo "Checking Promotion Works...\n";

$works = PrPromotionWork::with(['episode', 'episode.creativeWork'])->get();

foreach ($works as $work) {
    echo "Promotion Work ID: " . $work->id . "\n";
    echo "  Episode ID: " . $work->pr_episode_id . "\n";

    if ($work->episode) {
        echo "  Episode Title: " . $work->episode->title . "\n";

        $creative = $work->episode->creativeWork;
        if ($creative) {
            echo "  Creative Work ID: " . $creative->id . "\n";
            echo "  Shooting Schedule: " . ($creative->shooting_schedule ? $creative->shooting_schedule->format('Y-m-d') : 'NULL') . "\n";
            echo "  Shooting Time: " . ($creative->shooting_time ?? 'NULL') . "\n";
            echo "  Shooting Location: " . ($creative->shooting_location ?? 'NULL') . "\n";
        } else {
            echo "  hasCreativeWork: NO\n";

            // Try to find it manually to see if relation is broken
            $manualCreative = PrCreativeWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($manualCreative) {
                echo "  [WARNING] Creative Work exists manually (ID: {$manualCreative->id}) but relationship failed!\n";
            } else {
                echo "  [INFO] No Creative Work found for this episode.\n";
            }
        }
    } else {
        echo "  [ERROR] Episode not found!\n";
    }
    echo "--------------------------------------------------\n";
}
