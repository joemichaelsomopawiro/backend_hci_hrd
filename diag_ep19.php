<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\PromotionWork;

$episodeId = 19; // Assuming Ep 19 has ID 19
$episode = Episode::with('program')->find($episodeId);

if (!$episode) {
    // Try to find by title/number if ID 19 is wrong
    $episode = Episode::where('episode_number', 19)->first();
    if ($episode) $episodeId = $episode->id;
}

echo "EPISODE INFO:\n";
if ($episode) {
    echo "ID: " . $episode->id . "\n";
    echo "Title: " . $episode->title . "\n";
    echo "Air Date: " . $episode->air_date . "\n";
    echo "Production Date: " . $episode->production_date . "\n";
} else {
    echo "Episode 19 not found!\n";
}

echo "\nPROMOTION WORKS INFO:\n";
$works = PromotionWork::where('episode_id', $episodeId)->get();
foreach ($works as $w) {
    echo "ID: " . $w->id . "\n";
    echo "Type: " . $w->work_type . "\n";
    echo "Status: '" . $w->status . "'\n";
    echo "Files: " . (empty($w->file_links) ? 'No' : 'Yes') . "\n";
    echo "Proof: " . (empty($w->social_media_proof) ? 'No' : 'Yes') . "\n";
}

// Find Episode 1 as well
$ep1 = Episode::where('episode_number', 1)->first();
echo "\nEPISODE 1 INFO:\n";
if ($ep1) {
    echo "ID: " . $ep1->id . "\n";
    echo "Production Date: " . $ep1->production_date . "\n";
}
