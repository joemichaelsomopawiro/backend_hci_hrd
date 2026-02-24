<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\PrEditorWork;

echo "Updating existing Editor works with file_path to pending_qc status...\n\n";

// Find all editor works that have file_path but status is not pending_qc or completed
$works = PrEditorWork::whereNotNull('file_path')
    ->where('file_path', '!=', '')
    ->whereIn('status', ['editing', 'draft', 'revised'])
    ->get();

echo "Found " . $works->count() . " records to update.\n\n";

if ($works->count() === 0) {
    echo "No records need updating.\n";
    exit(0);
}

foreach ($works as $work) {
    $oldStatus = $work->status;
    $work->status = 'pending_qc';
    if (!$work->submitted_at) {
        $work->submitted_at = $work->updated_at ?? now();
    }
    $work->save();

    echo "Updated Work ID {$work->id} (Episode {$work->pr_episode_id}): {$oldStatus} → pending_qc\n";
}

echo "\n✅ Successfully updated " . $works->count() . " records.\n";
