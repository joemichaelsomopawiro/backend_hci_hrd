<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrPromotionWork;

// Hardcoded ID from previous debug output
$id = 14;

$work = PrPromotionWork::with(['episode.program', 'episode.creativeWork'])->find($id);

echo "JSON_START\n";
echo json_encode([
    'work_id' => $work->id,
    'episode_id' => $work->pr_episode_id,
    'has_creative' => $work->episode->creativeWork ? true : false,
    'creative_data' => $work->episode->creativeWork ? [
        'shooting_schedule' => $work->episode->creativeWork->shooting_schedule,
        'shooting_time' => $work->episode->creativeWork->shooting_time,
        'shooting_location' => $work->episode->creativeWork->shooting_location,
        'raw_creative' => $work->episode->creativeWork
    ] : null
], JSON_PRETTY_PRINT);
echo "\nJSON_END\n";
