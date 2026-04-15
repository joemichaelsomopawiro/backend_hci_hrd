<?php

use App\Models\Episode;
use App\Models\Deadline;
use App\Constants\WorkflowStep;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Sync Music Deadlines...\n";

// Ambil semua episode dari program kategori musik
$episodes = Episode::whereHas('program', function($q) {
    $q->where('category', 'musik');
})->get();

$count = 0;
foreach ($episodes as $episode) {
    $deadlines = Deadline::where('episode_id', $episode->id)
        ->where('auto_generated', true)
        ->get();

    foreach ($deadlines as $deadline) {
        $role = $deadline->role;
        $daysBefore = WorkflowStep::getDeadlineDaysForRole($role, 'musik');
        
        $newDeadlineDate = Carbon::parse($episode->air_date)->subDays($daysBefore);
        
        if ($deadline->deadline_date != $newDeadlineDate->format('Y-m-d H:i:s')) {
            $deadline->update([
                'deadline_date' => $newDeadlineDate,
                'description' => $deadline->description . ' (Sync with new 10/8 rules)'
            ]);
            $count++;
        }
    }
}

echo "Sync completed! Updated {$count} deadline records.\n";
