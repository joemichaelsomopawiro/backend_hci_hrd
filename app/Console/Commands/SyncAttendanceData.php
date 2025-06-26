<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceSyncService;
use Illuminate\Support\Facades\Log;

class SyncAttendanceData extends Command
{
    protected $signature = 'attendance:sync {--machine-id= : Specific machine ID to sync}';
    protected $description = 'Sync attendance data from attendance machines';

    protected $syncService;

    public function __construct(AttendanceSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        $this->info('Starting attendance data synchronization...');
        
        try {
            $results = $this->syncService->autoSyncAttendanceData();
            
            foreach ($results as $machineName => $result) {
                if ($result['success']) {
                    $this->info("✓ {$machineName}: Processed {$result['processed']} records");
                    if ($result['errors'] > 0) {
                        $this->warn("  ⚠ {$result['errors']} errors occurred");
                    }
                } else {
                    $this->error("✗ {$machineName}: {$result['message']}");
                }
            }
            
            $this->info('Attendance synchronization completed.');
            
        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            Log::error('Attendance sync command failed', ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
}