<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CreativeWork;
use App\Models\MusicSchedule;
use Illuminate\Support\Facades\Auth;

// Mock Auth
$user = \App\Models\User::first();
Auth::login($user);

echo "Testing getApprovedShootingSchedules logic...\n";

try {
    // 1. Get Creative Works
    $creativeSchedules = CreativeWork::with(['episode.program', 'createdBy'])
        ->where('status', 'approved')
        ->whereNotNull('shooting_schedule')
        ->limit(5)
        ->get();
    
    echo "Creative Works Count: " . $creativeSchedules->count() . "\n";

    // 2. Map Creative Works
    $calendarEvents = $creativeSchedules->map(function ($work) {
        return [
            'id' => 'creative_' . $work->id,
            'title' => 'Test Creative',
            'start' => $work->shooting_schedule,
            'type' => 'shooting'
        ];
    });

    echo "Mapped Creative Works type: " . get_class($calendarEvents) . "\n";

    // 3. Get Music Schedules
    $musicScheduleList = MusicSchedule::with(['musicSubmission.episode.program', 'creator'])
        ->whereIn('status', ['scheduled', 'confirmed'])
        ->where('schedule_type', 'shooting')
        ->limit(5)
        ->get();

    echo "Music Schedules Count: " . $musicScheduleList->count() . "\n";

    // 4. Map Music Schedules
    $musicEvents = $musicScheduleList->map(function ($schedule) {
        return [
            'id' => 'music_' . $schedule->id,
            'title' => 'Test Music',
            'start' => $schedule->getEffectiveDatetime(),
            'type' => 'shooting'
        ];
    });

    echo "Mapped Music Schedules type: " . get_class($musicEvents) . "\n";

    // 5. Merge Debugging
    echo "Merging...\n";
    echo "Calendar Events Class: " . get_class($calendarEvents) . "\n";
    echo "Music Events Class: " . get_class($musicEvents) . "\n";
    
    // Convert to base collection if necessary
    if ($calendarEvents instanceof \Illuminate\Database\Eloquent\Collection) {
        echo "Converting Calendar Events to Base Collection...\n";
        $calendarEvents = $calendarEvents->toBase();
    }
    if ($musicEvents instanceof \Illuminate\Database\Eloquent\Collection) {
        echo "Converting Music Events to Base Collection...\n";
        $musicEvents = $musicEvents->toBase();
    }

    try {
        $merged = $calendarEvents->merge($musicEvents);
        echo "Merged Count: " . $merged->count() . "\n";
        echo "Merge Success!\n";
    } catch (\Throwable $e) {
        echo "Merge Failed: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }

    // 6. Sort
    echo "Sorting...\n";
    $sorted = $merged->sortBy('start')->values();
    echo "Sort Success!\n";

} catch (\Throwable $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
