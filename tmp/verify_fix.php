<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEpisode;

$episode = PrEpisode::with([
    'qualityControlWork.reviewedBy',
    'qualityControlWork.createdBy',
    'managerDistribusiQcWork.reviewedBy',
    'managerDistribusiQcWork.createdBy',
    'broadcastingWork'
])->orderBy('id', 'desc')->first();

if ($episode) {
    echo "Episode ID: {$episode->id}\n";
    
    // Test QC name logic
    $qcReviewer = $episode->qualityControlWork?->reviewedBy?->name ?? $episode->qualityControlWork?->createdBy?->name;
    if (!$qcReviewer && $episode->qualityControlWork && !empty($episode->qualityControlWork->qc_checklist)) {
        $checklist = $episode->qualityControlWork->qc_checklist;
        $lastItem = end($checklist);
        $qcReviewer = $lastItem['checked_by'] ?? null;
    }
    echo "QC Reviewer Path Name: " . ($qcReviewer ?? 'NULL') . "\n";
    
    // Test Website URL logic
    $websiteUrl = $episode->broadcastingWork?->website_url;
    if (!$websiteUrl && $episode->broadcastingWork) {
        $metadata = $episode->broadcastingWork->metadata;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        if (is_array($metadata)) {
            $websiteUrl = $metadata['jetstream_url'] ?? $metadata['jetstream_link'] ?? null;
        }
    }
    echo "Website URL Path Value: " . ($websiteUrl ?? 'NULL') . "\n";
}
