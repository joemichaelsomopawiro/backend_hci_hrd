<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\Deadline;

$epId = 14; 
$episode = Episode::with(['program', 'deadlines'])->find($epId);

if (!$episode) {
    echo "Episode not found\n";
    exit;
}

echo "Episode: {$episode->episode_number} - {$episode->title}\n";
echo "Program Category: " . ($episode->program->category ?? 'N/A') . "\n";
echo "Deadlines:\n";

foreach ($episode->deadlines as $d) {
    $assignee = $d->assignee ? $d->assignee->name : 'Unassigned';
    echo "- Role: {$d->role} (Label: {$d->role_label}) | Assigned: {$assignee} | ID: {$d->assigned_user_id}\n";
}
