<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SyncAttendanceData::class,
        \App\Console\Commands\SyncUsersToMachines::class,
        \App\Console\Commands\AttendanceMachineStatus::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync attendance data every 15 minutes
        $schedule->command('attendance:sync')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Sync users to machines daily at 2 AM
        $schedule->command('attendance:sync-users')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();
        
        // Check machine status every hour
        $schedule->command('attendance:machine-status')
                 ->hourly()
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
