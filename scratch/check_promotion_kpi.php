<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PromotionWork;
use App\Models\Episode;

$episode = Episode::where('episode_number', 20)->whereHas('program', function($q) { $q->where('name', 'like', '%test 2%'); })->first();
if (!$episode) {
    echo "Episode 20 not found\n";
    exit;
}

echo "Episode ID: " . $episode->id . "\n";
$works = PromotionWork::where('episode_id', $episode->id)->get();
foreach ($works as $work) {
    echo "Work ID: " . $work->id . "\n";
    echo "Created By: " . $work->created_by . " (" . $work->createdBy->name . ")\n";
    echo "Status: " . $work->status . "\n";
    echo "BTS Links: " . count($work->file_links ?? []) . "\n";
}
