<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$allQc = PrManagerDistribusiQcWork::with('episode.program')->orderBy('created_at', 'desc')->get();
echo "START_QC_LIST\n";
foreach ($allQc as $w) {
    echo "ID:{$w->id}|EpID:{$w->pr_episode_id}|Title:".($w->episode->title ?? 'N/A')."|Program:".($w->episode->program->name ?? 'N/A')."|Status:{$w->status}\n";
}
echo "END_QC_LIST\n";
