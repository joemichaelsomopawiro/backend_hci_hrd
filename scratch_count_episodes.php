<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
use App\Models\Episode;

$program = Program::latest()->first();
if ($program) {
    echo "Latest Program: {$program->name} (ID: {$program->id})\n";
    echo "Start Date: " . $program->start_date->format('Y-m-d') . "\n";
    echo "Episodes Count (Relation): " . $program->episodes()->count() . "\n";
    echo "Episodes Count (Direct): " . Episode::where('program_id', $program->id)->count() . "\n";
    
    $lastEpisode = Episode::where('program_id', $program->id)->orderBy('episode_number', 'desc')->first();
    if ($lastEpisode) {
        echo "Last Episode Number: {$lastEpisode->episode_number}\n";
        echo "Last Episode Air Date: " . $lastEpisode->air_date->format('Y-m-d') . "\n";
    }
} else {
    echo "No programs found.\n";
}
