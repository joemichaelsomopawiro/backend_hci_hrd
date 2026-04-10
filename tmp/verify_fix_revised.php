<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEpisode;
use App\Models\PrBroadcastingWork;
use App\Models\PrQualityControlWork;

// Find an episode with broadcasting metadata
$bw = PrBroadcastingWork::whereNotNull('metadata')->orderBy('id', 'desc')->first();
if ($bw) {
    echo "--- Testing Broadcasting (BW ID: {$bw->id}) ---\n";
    $metadata = $bw->metadata;
    if (is_string($metadata)) $metadata = json_decode($metadata, true);
    $url = $metadata['jetstream_url'] ?? $metadata['jetstream_link'] ?? 'NOT FOUND';
    echo "Jetstream Link: " . $url . "\n";
} else {
    echo "No broadcasting metadata found to test.\n";
}

// Find an episode with QC checklist
$qc = PrQualityControlWork::whereNotNull('qc_checklist')->orderBy('id', 'desc')->first();
if ($qc) {
    echo "\n--- Testing QC (QC ID: {$qc->id}) ---\n";
    $checklist = $qc->qc_checklist;
    $lastItem = end($checklist);
    $reviewer = $lastItem['checked_by'] ?? 'NOT FOUND';
    echo "Checked By (from checklist): " . $reviewer . "\n";
} else {
    echo "No QC checklist found to test.\n";
}
