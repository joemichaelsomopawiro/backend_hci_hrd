<?php

// Load Laravel Bootstrap
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\Program;
use Illuminate\Support\Facades\Log;

echo "--- STARTING AGGRESSIVE FORCE SYNC MUSIC DEADLINES ---\n";

// 1. Pastikan program pengetesan masuk kategori musik
$targetPrograms = Program::where('name', 'LIKE', '%test%')
    ->orWhere('category', 'musik')
    ->get();

echo "Found " . $targetPrograms->count() . " target programs.\n";

foreach ($targetPrograms as $program) {
    if ($program->category !== 'musik') {
        echo "Updating Program '{$program->name}' category to 'musik'...\n";
        $program->category = 'musik';
        $program->save();
    }
}

// 2. Ambil semua episode dari program kategori 'musik'
$musicEpisodes = Episode::whereHas('program', function($q) {
    $q->where('category', 'musik');
})->get();

echo "Found " . $musicEpisodes->count() . " music episodes starting sync...\n";

foreach ($musicEpisodes as $episode) {
    try {
        echo "Processing Episode: {$episode->title} (ID: {$episode->id})...\n";
        
        // Picu fungsi generateDeadlines untuk membuat role baru (music_arr_song = 15 hari, dsb)
        $episode->generateDeadlines();
        
        // Sinkronkan PJ (Assigned User) dari Production Team
        $episode->syncTeamAssignments();
        
        echo "Successfully synced deadlines for '{$episode->title}'\n";
    } catch (\Exception $e) {
        echo "ERROR on Episode {$episode->id}: " . $e->getMessage() . "\n";
    }
}

echo "\n--- SYNC COMPLETED ---\n";
echo "Silakan refresh dashboard Active Productions Anda.\n";
echo "Deadline 'Song Proposal' SEHARUSNYA sekarang muncul dengan 15 hari.\n";
