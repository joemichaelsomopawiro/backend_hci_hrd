<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEpisode;
use App\Models\PrBroadcastingWork;
use App\Models\PrQualityControlWork;

echo "--- BROADCASTING DATA ---\n";
$broadcasting = PrBroadcastingWork::orderBy('id', 'desc')->take(10)->get();
foreach ($broadcasting as $bw) {
    echo "ID: {$bw->id} | Ep: {$bw->pr_episode_id} | Web URL: " . ($bw->website_url ?: 'NULL') . " | Meta: " . json_encode($bw->metadata) . "\n";
}

echo "\n--- QC DATA ---\n";
$qc = PrQualityControlWork::with(['createdBy', 'reviewedBy'])->orderBy('id', 'desc')->take(10)->get();
foreach ($qc as $q) {
    echo "ID: {$q->id} | Ep: {$q->pr_episode_id} | Created: " . ($q->createdBy->name ?? 'NULL') . " | Reviewed: " . ($q->reviewedBy->name ?? 'NULL') . " | Checklist Sample: " . json_encode(array_slice($q->qc_checklist ?? [], 0, 1)) . "\n";
}
