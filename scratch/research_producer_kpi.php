<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Deadline;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use Carbon\Carbon;

// Assuming user ID 1 is the one we are interested in, or let's search for "producer" role
$user = User::where('role', 'Producer')->first();
if (!$user) {
    echo "No Producer user found.\n";
    exit;
}

echo "Checking KPI Data for User: {$user->name} (ID: {$user->id})\n";

$deadlines = Deadline::with(['episode.program'])
    ->where('assigned_user_id', $user->id)
    ->whereHas('episode', function($q) {
        $q->whereYear('air_date', 2026);
    })
    ->get();

echo "\n--- Assigned Deadlines ---\n";
foreach ($deadlines as $d) {
    $progName = $d->episode->program->name ?? 'Unknown';
    echo "Ep: {$d->episode->episode_number}, Role: {$d->role}, Deadline: {$d->deadline_date}, Program: {$progName}\n";
}

echo "\n--- Song Proposals (MusicArrangement) for this user ---\n";
// Producer approves song proposals, so let's check arrangements for episodes where they have the role
$arrangements = MusicArrangement::with('episode.program')->get();
foreach ($arrangements as $a) {
    echo "Ep: {$a->episode->episode_number}, Status: {$a->status}, SongApprovedAt: {$a->song_approved_at}\n";
}

echo "\n--- Creative Works for this user ---\n";
$creativeWorks = CreativeWork::with('episode.program')->get();
foreach ($creativeWorks as $cw) {
    echo "Ep: {$cw->episode->episode_number}, Status: {$cw->status}, ReviewedAt: {$cw->reviewed_at}\n";
}
