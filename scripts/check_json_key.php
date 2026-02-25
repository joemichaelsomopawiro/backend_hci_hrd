<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\PrPromotionWork;

// Find a work that definitely has a creative work
$work = PrPromotionWork::whereHas('episode.creativeWork')->with(['episode.creativeWork'])->first();

if ($work) {
    $json = $work->toJson();
    echo $json;
} else {
    echo "No work found";
}
