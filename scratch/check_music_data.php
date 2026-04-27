<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\MusicSchedule;
use Carbon\Carbon;

$year = 2026;
$month = 4;
$startDate = "$year-0$month-01 00:00:00";
$endDate = "$year-0$month-30 23:59:59";

echo "Checking for Music Events in $year-$month...\n";

$episodes = Episode::whereHas('program', function($q) {
        $q->where('category', 'musik');
    })
    ->whereBetween('air_date', [$startDate, $endDate])
    ->get();

echo "Episodes found: " . $episodes->count() . "\n";
foreach ($episodes as $ep) {
    echo " - ID: {$ep->id}, Date: {$ep->air_date}, Program: " . ($ep->program->name ?? 'N/A') . "\n";
}

$schedules = MusicSchedule::whereHas('musicSubmission.episode.program', function($q) {
        $q->where('category', 'musik');
    })
    ->whereBetween('scheduled_datetime', [$startDate, $endDate])
    ->get();

echo "Music Schedules found: " . $schedules->count() . "\n";
foreach ($schedules as $s) {
    echo " - ID: {$s->id}, Date: {$s->scheduled_datetime}, Type: {$s->schedule_type}\n";
}
