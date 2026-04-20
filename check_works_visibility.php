<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BroadcastingWork;

echo "📊 Broadcasting Works Database Check:\n";
$allWorks = BroadcastingWork::select('id', 'status', 'episode_id', 'created_at')->get();

if ($allWorks->isEmpty()) {
    echo "❌ No broadcasting works found in the database.\n";
} else {
    echo "✅ Found " . $allWorks->count() . " records:\n";
    foreach ($allWorks as $work) {
        echo "- ID: {$work->id}, Status: '{$work->status}', Episode ID: {$work->episode_id}, Created: {$work->created_at}\n";
    }
}

echo "\n🔍 Filtering for 'Editor Approval' tab logic:\n";
$pendingStatuses = ['pending_approval', 'reviewing'];
$historyStatuses = ['pending', 'rejected', 'preparing', 'uploading', 'processing', 'scheduled', 'published', 'failed', 'cancelled'];

$pendingCount = $allWorks->whereIn('status', $pendingStatuses)->count();
$historyCount = $allWorks->whereIn('status', $historyStatuses)->count();

echo "Pending for Review: $pendingCount\n";
echo "Recently Processed: $historyCount\n";
