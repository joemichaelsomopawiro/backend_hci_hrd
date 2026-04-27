<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Program;

$programName = 'program tayang baru 53';
$program = Program::where('name', $programName)->first();

if (!$program) {
    echo "Program not found: $programName\n";
    exit;
}

echo "Program ID: " . $program->id . "\n";
echo "Program Name: " . $program->name . "\n";
echo "Start Date (raw): " . $program->getRawOriginal('start_date') . "\n";
echo "Start Date (formatted): " . $program->start_date->format('Y-m-d') . "\n";
echo "Air Time: " . ($program->air_time ? $program->air_time->format('H:i') : 'null') . "\n";

echo "\nFirst 5 Episodes:\n";
$episodes = $program->episodes()->limit(5)->get();
foreach ($episodes as $ep) {
    echo "Ep " . $ep->episode_number . ": " . $ep->air_date->format('Y-m-d') . " (Status: " . $ep->status . ")\n";
}
