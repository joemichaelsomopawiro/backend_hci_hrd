<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$all = PrManagerDistribusiQcWork::with('episode.program')->get();
echo "START_DETAILED_QC_LIST\n";
foreach ($all as $w) {
    $ep = $w->episode;
    $prog = $ep ? $ep->program : null;
    echo "ID:{$w->id}|EpID:" . ($ep->id ?? 'NULL') . "|Title:" . ($ep->title ?? 'N/A') . "|Prog:" . ($prog->name ?? 'N/A') . "|Status:{$w->status}\n";
}
echo "END_DETAILED_QC_LIST\n";
