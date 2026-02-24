<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrCreativeWork;
use App\Models\PrPromotionWork;
use App\Models\PrEpisode;

echo "Searching for Creative Work with shooting_schedule on 2026-02-02...\n";

// Search for the date (approximate match as it might have time)
$creatives = PrCreativeWork::where('shooting_schedule', 'like', '2026-02-02%')->get();

echo "Found " . $creatives->count() . " matching Creative Works.\n";

foreach ($creatives as $creative) {
    echo "================================\n";
    echo "Creative Work ID: " . $creative->id . "\n";
    echo "Episode ID: " . $creative->pr_episode_id . "\n";
    echo "Shooting Schedule: " . $creative->shooting_schedule . "\n";

    $episode = PrEpisode::find($creative->pr_episode_id);
    if ($episode) {
        echo "Episode Found: " . $episode->title . " (EP " . $episode->episode_number . ")\n";

        // Check reverse relationship
        $epCreative = $episode->creativeWork;
        echo "Episode->creativeWork: " . ($epCreative ? "OK (ID: {$epCreative->id})" : "NULL") . "\n";

        // Check Promotion Work
        $promo = PrPromotionWork::where('pr_episode_id', $episode->id)->first();
        if ($promo) {
            echo "Promotion Work Found (ID: " . $promo->id . ")\n";

            // Check Loading
            $loadedPromo = PrPromotionWork::with('episode.creativeWork')->find($promo->id);
            echo "Loaded Promo->episode->creativeWork: " . ($loadedPromo->episode->creativeWork ? "OK (ID: {$loadedPromo->episode->creativeWork->id})" : "NULL") . "\n";
        } else {
            echo "No Promotion Work found for this episode.\n";
        }
    } else {
        echo "Episode NOT Found!\n";
    }
}
