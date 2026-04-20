<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EditorWork;
use App\Models\BroadcastingWork;
use Illuminate\Support\Facades\DB;

echo "📊 Music EditorWork Status Breakdown:\n";
$statuses = EditorWork::select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();

foreach ($statuses as $s) {
    echo "- '{$s->status}': {$s->count}\n";
}

echo "\n🔍 Checking for logic inconsistencies:\n";
// Are there any works that are 'submitted' or similar that should have created a BroadcastingWork?
// Let's look for statuses like 'submitted', 'reviewing', 'pending_qc', etc.
$potentialSubmitted = EditorWork::whereIn('status', ['submitted', 'pending_qc', 'reviewing', 'completed'])->get();

foreach ($potentialSubmitted as $ew) {
    $bw = BroadcastingWork::where('episode_id', $ew->episode_id)->exists();
    if (!$bw) {
        echo "⚠️ Potential Disconnect: EditorWork ID {$ew->id} is in status '{$ew->status}' but has no matching BroadcastingWork!\n";
    }
}
