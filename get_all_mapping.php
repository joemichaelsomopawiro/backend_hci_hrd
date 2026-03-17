<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$all = PrManagerDistribusiQcWork::all();
echo "TOTAL_RECORDS: " . $all->count() . "\n";
foreach ($all as $w) {
    echo "ID:{$w->id}|EpID:{$w->pr_episode_id}|Status:{$w->status}\n";
}
