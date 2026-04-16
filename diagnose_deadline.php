<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\Program;

ob_start();
echo "--- DIAGNOSIS START ---\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";

$episode = Episode::where('title', 'LIKE', '%test 1%')->where('episode_number', 7)->first();

if (!$episode) {
    // Try finding by program name
    $program = Program::where('name', 'LIKE', '%test 1%')->first();
    if ($program) {
        $episode = $program->episodes()->where('episode_number', 7)->first();
    }
}

if ($episode) {
    echo "Episode: " . $episode->title . " (ID: " . $episode->id . ")\n";
    echo "Program: " . $episode->program->name . " (Category: '" . $episode->program->category . "')\n";
    
    $deadlines = $episode->deadlines;
    echo "Deadlines count: " . $deadlines->count() . "\n";
    foreach ($deadlines as $dl) {
        echo "- Role: " . $dl->role . ", Date: " . $dl->deadline_date . "\n";
    }
} else {
    echo "Episode not found for diagnosis.\n";
}

echo "--- DIAGNOSIS END ---\n";
$output = ob_get_clean();
file_put_contents(__DIR__ . '/diagnose_results.txt', $output);
echo "Diagnosis finished. Results saved to diagnose_results.txt\n";
