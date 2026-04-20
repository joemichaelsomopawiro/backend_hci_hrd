<?php
// C:\laragon\www\backend_hci_hrd\scratch\inspect_music_data.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MusicArrangement;
use App\Models\Episode;
use Illuminate\Support\Facades\DB;

$userId = 1; // Music Arranger Test
$month = 4;
$year = 2026;

echo "--- INSPECTING MUSIC ARRANGEMENTS FOR USER $userId (April 2026) ---\n";

$arrangements = MusicArrangement::where('created_by', $userId)
    ->with('episode')
    ->get();

foreach ($arrangements as $m) {
    echo "ID: {$m->id}\n";
    echo "Episode ID: {$m->episode_id}\n";
    echo "Status: {$m->status}\n";
    echo "Created At: {$m->created_at}\n";
    echo "Song Approved At: {$m->song_approved_at}\n";
    echo "Arrangement Submitted At: {$m->arrangement_submitted_at}\n";
    echo "Submitted At: {$m->submitted_at}\n";
    echo "Reviewed At: {$m->reviewed_at}\n";
    echo "Air Date: " . ($m->episode->air_date ?? 'N/A') . "\n";
    echo "---------------------------\n";
}

$epIds = $arrangements->pluck('episode_id')->toArray();
echo "Total arrangements found: " . count($arrangements) . "\n";
echo "Episode IDs: " . implode(', ', $epIds) . "\n";
