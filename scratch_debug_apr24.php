<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Episode;
use Carbon\Carbon;

$date = '2026-04-24';
$eps = Episode::whereDate('air_date', $date)->with('program')->get();

echo "Episodes on {$date}:\n";
if ($eps->isEmpty()) {
    echo "NONE FOUND\n";
} else {
    foreach ($eps as $e) {
        echo "ID: {$e->id} | Program: {$e->program->name} | Status: {$e->status} | Manager ID: " . ($e->program->manager_program_id ?? 'NULL') . "\n";
    }
}

$user = \App\Models\User::where('role', 'Program Manager')->first();
if ($user) {
    echo "\nSample Program Manager: ID {$user->id} | Name: {$user->name}\n";
}
