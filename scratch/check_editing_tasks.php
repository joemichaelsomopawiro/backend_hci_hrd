<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SoundEngineerEditing;
use App\Models\SoundEngineerRecording;

$recordings = SoundEngineerRecording::where('status', 'completed')->get();
echo "Completed Recordings: " . $recordings->count() . "\n";

foreach ($recordings as $r) {
    $editing = SoundEngineerEditing::where('sound_engineer_recording_id', $r->id)->first();
    echo "Recording ID: {$r->id}, Episode ID: {$r->episode_id}, Editing Task: " . ($editing ? "EXISTS (ID: {$editing->id}, Assigned: {$editing->sound_engineer_id}, Status: {$editing->status})" : "MISSING") . "\n";
}
