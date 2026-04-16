<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\Program;

// Find your test episode
$episode = Episode::where('episode_number', 18)
    ->whereHas('program', function($q) {
        $q->where('name', 'like', '%test%');
    })->first();

if ($episode) {
    echo "Checking Episode: " . $episode->program->name . " ep " . $episode->episode_number . "\n";
    echo "Program Category: " . $episode->program->category . "\n";
    
    // Check if deadlines exist in DB
    $deadlines = $episode->deadlines()->pluck('deadline_date', 'role')->toArray();
    echo "Deadlines in DB: " . json_encode($deadlines, JSON_PRETTY_PRINT) . "\n";
    
    // Simulate what the controller does (keys)
    $order = [
        'program_active', 'song_proposal', 'song_proposal_approval', 
        'music_arrangement_link', 'arrangement_approval', 'vocal_recording'
    ];
    
    foreach ($order as $key) {
        $roleMap = [
            'program_active' => 'program_manager',
            'song_proposal' => 'musik_arr_song',
            'song_proposal_approval' => 'producer_acc_song',
            'music_arrangement_link' => 'musik_arr_lagu',
            'arrangement_approval' => 'producer_acc_lagu',
            'vocal_recording' => 'tim_vocal_coord'
        ];
        
        $role = $roleMap[$key] ?? $key;
        $exists = isset($deadlines[$role]);
        echo "Step [$key] -> Role [$role] -> Exists: " . ($exists ? "YES ($deadlines[$role])" : "NO") . "\n";
    }
} else {
    echo "Episode not found.\n";
}
