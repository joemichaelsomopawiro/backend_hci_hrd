<?php
use App\Models\User;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Models\Deadline;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$nik = '1234567890123456';
$user = User::where('nik', $nik)->first();

if (!$user) {
    echo "User not found\n";
    exit;
}

echo "User ID: {$user->id}\n";
echo "User Role: {$user->role}\n";

$episodes = [18, 20];

foreach ($episodes as $num) {
    $ep = Episode::where('episode_number', $num)->first();
    if (!$ep) {
        echo "Episode $num not found\n";
        continue;
    }
    
    echo "\n--- Episode $num (ID: {$ep->id}) ---\n";
    echo "Air Date: " . ($ep->air_date ? $ep->air_date->toDateString() : 'N/A') . "\n";
    
    $arrangement = MusicArrangement::where('episode_id', $ep->id)->first();
    if ($arrangement) {
        echo "Arrangement ID: {$arrangement->id}\n";
        echo "Status: {$arrangement->status}\n";
        echo "Created By: {$arrangement->created_by}\n";
        echo "Reviewed By: {$arrangement->reviewed_by}\n";
        echo "Created At: {$arrangement->created_at}\n";
        echo "Song Approved At: {$arrangement->song_approved_at}\n";
        echo "Arrangement Submitted At: {$arrangement->arrangement_submitted_at}\n";
        echo "Reviewed At: {$arrangement->reviewed_at}\n";
    } else {
        echo "No arrangement found for Episode $num\n";
    }
    
    $deadlines = Deadline::where('episode_id', $ep->id)
        ->where('assigned_user_id', $user->id)
        ->get();
    
    foreach ($deadlines as $d) {
        echo "Deadline: Role={$d->role}, Date={$d->deadline_date}, Assigned={$d->assigned_user_id}\n";
    }
}
