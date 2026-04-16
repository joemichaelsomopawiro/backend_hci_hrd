<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KpiPointSetting;
use App\Models\MusicEpisode;
use App\Models\PrEpisode;
use App\Models\User;

echo "--- KPI Point Settings (Musik Song Proposal) ---\n";
$settings = KpiPointSetting::where('program_type', 'musik')
    ->whereIn('role', ['musik_arr_song', 'producer_acc_song'])
    ->get();

foreach ($settings as $s) {
    echo "Role: {$s->role}, OnTime: {$s->points_on_time}, Late: {$s->points_late}, NotDone: {$s->points_not_done}\n";
}

echo "\n--- Episode 19 Check ---\n";
// Check in both PrEpisode and MusicEpisode (assuming music episodes might be in either depending on schema)
$musicEp19 = \DB::table('music_episodes')->where('episode_number', 19)->first();
if ($musicEp19) {
    echo "Music Episode 19 found: ID {$musicEp19->id}, Air Date: {$musicEp19->air_date}\n";
} else {
    echo "Music Episode 19 not found in music_episodes table.\n";
}

$prEp19 = \App\Models\PrEpisode::where('episode_number', '19')->first();
if ($prEp19) {
    echo "PR Episode 19 found: ID {$prEp19->id}, Air Date: {$prEp19->air_date}\n";
}

echo "\n--- User Role Check ---\n";
$firstUser = User::first();
if ($firstUser) {
    echo "User ID: {$firstUser->id}, Name: {$firstUser->name}, Role: {$firstUser->role}\n";
}
