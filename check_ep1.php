<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\PromotionWork;

$ep1 = Episode::where('episode_number', 1)->first();
if ($ep1) {
    echo "Ep 1 ID: {$ep1->id}\n";
    $works = PromotionWork::where('episode_id', $ep1->id)->get();
    foreach ($works as $w) {
        echo "- Type: {$w->work_type}, Status: {$w->status}, CreatedBy: {$w->created_by}\n";
    }
} else {
    echo "Ep 1 not found\n";
}
