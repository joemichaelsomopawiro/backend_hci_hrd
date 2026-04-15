<?php

use App\Models\Program;
use App\Models\Episode;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Sync for Music Program Team Assignments...\n";

// 1. Get all programs that are 'Program Musik' (Category like 'Program Musik' or 'Kebutuhan Vocal')
// Based on current DB, Program Musik April has ID 130
$programs = Program::where('name', 'LIKE', '%Musik%')
    ->orWhere('category', 'LIKE', '%Vocal%')
    ->get();

foreach ($programs as $program) {
    echo "Processing Program: {$program->name} (ID: {$program->id})\n";
    
    $episodes = $program->episodes;
    foreach ($episodes as $episode) {
        echo "  - Syncing Episode: {$episode->title} (ID: {$episode->id})... ";
        
        $result = $episode->syncTeamAssignments();
        
        if ($result) {
            echo "DONE\n";
        } else {
            echo "SKIPPED (No team or error)\n";
        }
    }
}

echo "All sync operations completed.\n";
