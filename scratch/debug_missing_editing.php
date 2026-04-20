<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SoundEngineerEditing;
use App\Models\SoundEngineerRecording;
use App\Models\Episode;

$episodes = Episode::where('episode_number', 18)->with('program')->get();
echo "Episodes with number 18: " . $episodes->count() . "\n";

foreach ($episodes as $ep) {
    echo "--- Episode ID: {$ep->id}, Program: {$ep->program->name} ---\n";
    
    // Check Creative Works status
    $creativeWorks = $ep->creativeWorks()->get();
    echo "Creative Works Count: " . $creativeWorks->count() . "\n";
    foreach ($creativeWorks as $cw) {
        echo "  - CW ID: {$cw->id}, Status: {$cw->status}\n";
    }

    // Check Sound Engineer Recording
    $recording = SoundEngineerRecording::where('episode_id', $ep->id)->first();
    echo "Recording: " . ($recording ? "ID {$recording->id}, Status: {$recording->status}" : "MISSING") . "\n";

    // Check Sound Engineer Editing
    $editing = SoundEngineerEditing::where('episode_id', $ep->id)->get();
    echo "Editing Works Count: " . $editing->count() . "\n";
    foreach ($editing as $ed) {
        echo "  - Editing ID: {$ed->id}, Status: {$ed->status}, SE ID: {$ed->sound_engineer_id}\n";
    }
}
