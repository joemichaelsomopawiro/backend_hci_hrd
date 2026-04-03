<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\EditorWork;
use App\Models\User;

$works = EditorWork::with(['episode.program', 'createdBy'])->latest()->get();

echo "Total Works: " . $works->count() . "\n";
foreach ($works as $work) {
    $episode = $work->episode;
    $program = $episode?->program;
    echo "ID: {$work->id}, Episode: " . ($episode ? $episode->id : 'NULL') . ", Status: {$work->status}, Program: " . ($program ? "{$program->id} ({$program->name})" : 'NULL') . "\n";
}
