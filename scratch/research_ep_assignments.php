<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
use App\Models\Episode;
use App\Models\Deadline;

// 1. Check Programs
echo "--- Programs ---\n";
$programs = Program::whereIn('id', [130])->orWhere('name', 'like', '%test%')->get();
foreach ($programs as $p) {
    echo "ID: {$p->id} | Name: '{$p->name}' | Category: {$p->category}\n";
}

// 2. Check Episodes 14-19
echo "\n--- Episodes 14-19 ---\n";
$episodes = Episode::whereBetween('episode_number', [14, 19])->with('program')->get();
foreach ($episodes as $e) {
    echo "ID: {$e->id} | Num: {$e->episode_number} | AirDate: {$e->air_date} | Program: " . ($e->program->name ?? 'None') . " (ID: {$e->program_id})\n";
}

// 3. User Deadlines for April 2026
echo "\n--- User Deadlines (for current user) ---\n";
$user = auth()->user() ?? \App\Models\User::where('role', 'Producer')->first();
if ($user) {
    echo "User: {$user->name} (ID: {$user->id})\n";
    $deadlines = Deadline::where('assigned_user_id', $user->id)
        ->with('episode.program')
        ->orderBy('deadline_date')
        ->get();
    foreach ($deadlines as $d) {
        echo "- Ep {$d->episode->episode_number} | Role: {$d->role} | Date: {$d->deadline_date} | Program: " . ($d->episode->program->name ?? 'None') . "\n";
    }
}
