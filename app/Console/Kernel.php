<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
       
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync attendance data dari mesin Solution X304 setiap 15 menit
        $schedule->command('attendance:sync --process')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->name('attendance-sync-x304')
                 ->description('Sync attendance data dari mesin Solution X304')
                 ->appendOutputTo(storage_path('logs/attendance-sync.log'));

        // Proses ulang attendance hari ini setiap jam (untuk memastikan data lengkap)
        $schedule->call(function () {
            $processingService = app(\App\Services\AttendanceProcessingService::class);
            $result = $processingService->processTodayAttendance();
            Log::info('Hourly attendance processing completed', $result);
        })->hourly()
          ->name('attendance-hourly-process')
          ->withoutOverlapping()
          ->description('Proses ulang attendance hari ini');

        // Generate summary harian setiap pagi jam 6
        $schedule->call(function () {
            $yesterday = now()->subDay()->format('Y-m-d');
            $processingService = app(\App\Services\AttendanceProcessingService::class);
            $summary = $processingService->getAttendanceSummary($yesterday);
            Log::info("Daily attendance summary for {$yesterday}", ['summary' => $summary]);
        })->dailyAt('06:00')
          ->name('daily-attendance-summary')
          ->description('Generate daily attendance summary');

        // Auto-expire leave requests yang sudah melewati tanggal mulai cuti
        $schedule->command('leave:expire')
                 ->dailyAt('07:00')
                 ->withoutOverlapping()
                 ->name('auto-expire-leave-requests')
                 ->description('Auto-expire leave requests yang sudah melewati tanggal mulai cuti')
                 ->appendOutputTo(storage_path('logs/leave-expire.log'));

        // Sinkronisasi status cuti ke attendance setiap pagi jam 7:30
        $schedule->command('attendance:sync-leave --today')
                 ->dailyAt('07:30')
                 ->withoutOverlapping()
                 ->name('sync-leave-to-attendance')
                 ->description('Sinkronisasi status cuti yang approved ke tabel attendance')
                 ->appendOutputTo(storage_path('logs/leave-attendance-sync.log'));

        // Reset jatah cuti tahunan otomatis setiap 1 Januari jam 00:01
        $schedule->command('leave:reset-annual')
                 ->yearly()
                 ->at('00:01')
                 ->withoutOverlapping()
                 ->name('annual-leave-reset')
                 ->description('Reset jatah cuti tahunan untuk semua karyawan dengan standar default baru')
                 ->appendOutputTo(storage_path('logs/annual-leave-reset.log'));
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
