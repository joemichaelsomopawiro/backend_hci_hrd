<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$w = PrManagerDistribusiQcWork::find(2);
if ($w) {
    echo "ID: " . $w->id . "\n";
    echo "EpID: " . $w->pr_episode_id . "\n";
    echo "Status: " . $w->status . "\n";
    echo "Created At: " . $w->created_at . "\n";
    echo "Deleted At: " . $w->deleted_at . "\n";
    $ep = $w->episode;
    echo "Episode Title: " . ($ep->title ?? 'N/A') . "\n";
} else {
    echo "ID 2 NOT FOUND\n";
}
