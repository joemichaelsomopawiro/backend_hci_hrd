<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrProgram;
use App\Models\PrEpisode;

$program = PrProgram::where('name', 'LIKE', '%test 2%')->first();

if (!$program) {
    echo "Program 'test 2' not found.\n";
    exit;
}

echo "Found Program: " . $program->name . " (ID: " . $program->id . ")\n";

$episodes = PrEpisode::where('program_id', $program->id)
    ->whereIn('episode_number', [2, 3, 4, 5])
    ->get();

foreach ($episodes as $episode) {
    echo "Episode " . $episode->episode_number . " (ID: " . $episode->id . ") - Status: " . $episode->status . "\n";
}
