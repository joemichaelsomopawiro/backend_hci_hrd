<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrPromotionWork;

$works = PrPromotionWork::with(['episode', 'episode.creativeWork'])->get();
$results = [];

foreach ($works as $work) {
    $creative = $work->episode ? $work->episode->creativeWork : null;
    $results[] = [
        'promotion_work_id' => $work->id,
        'episode_id' => $work->pr_episode_id,
        'episode_title' => $work->episode->title ?? null,
        'has_creative_work' => $creative ? true : false,
        'shooting_schedule' => $creative->shooting_schedule ?? null,
        'shooting_time' => $creative->shooting_time ?? null,
        'shooting_location' => $creative->shooting_location ?? null,
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);
