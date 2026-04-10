<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEpisode;

$episodes = PrEpisode::with([
    'broadcastingWork',
    'qualityControlWork.createdBy',
    'managerDistribusiQcWork.createdBy',
    'workflowProgress.assignedUser'
])->orderBy('id', 'desc')->take(10)->get();

foreach ($episodes as $e) {
    echo "Episode ID: {$e->id}\n";
    echo "  Title: {$e->title}\n";
    echo "  Website URL: " . ($e->broadcastingWork->website_url ?? 'NULL') . "\n";
    echo "  QC Work User: " . ($e->qualityControlWork->createdBy->name ?? 'NULL') . "\n";
    echo "  Distribusi QC User: " . ($e->managerDistribusiQcWork->createdBy->name ?? 'NULL') . "\n";
    
    $step7 = $e->workflowProgress->where('workflow_step', 7)->first();
    $step8 = $e->workflowProgress->where('workflow_step', 8)->first();
    
    echo "  Step 7 Assigned: " . ($step7?->assignedUser?->name ?? 'NULL') . " (Status: " . ($step7?->status ?? 'N/A') . ")\n";
    echo "  Step 8 Assigned: " . ($step8?->assignedUser?->name ?? 'NULL') . " (Status: " . ($step8?->status ?? 'N/A') . ")\n";
    echo "---------------------------------\n";
}
