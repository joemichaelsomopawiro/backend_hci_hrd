<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Program;
use Carbon\Carbon;

// Mock program data
$startDate = '2026-04-24';
$airTime = '19:00';

$program = new Program([
    'name' => 'Test Program debug',
    'start_date' => $startDate,
    'air_time' => $airTime,
    'category' => 'musik'
]);

// We won't save to DB, just check the generation logic in generateEpisodes
// But since generateEpisodes does DB insert, we can't call it easily without side effects.
// Let's just simulate the logic here manually based on what's in Program.php

echo "Debug Generation Logic:\n";
echo "Start Date: $startDate\n";

$timeParts = explode(':', $airTime);
$hour = (int)$timeParts[0];
$minute = (int)$timeParts[1];

$startDateCarbon = Carbon::parse($startDate)->setTime($hour, $minute, 0);
$firstOccurrence = $startDateCarbon->copy();

echo "First Occurrence (Ep 1): " . $firstOccurrence->format('Y-m-d H:i:s') . "\n";

for ($i = 1; $i <= 3; $i++) {
    $airDate = $firstOccurrence->copy();
    if ($i > 1) {
        $airDate->addWeeks($i - 1);
    }
    echo "Episode $i Air Date: " . $airDate->format('Y-m-d H:i:s') . "\n";
}
