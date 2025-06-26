<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceSyncService;
use App\Models\AttendanceMachine;
use Illuminate\Support\Facades\Log;

class SyncUsersToMachines extends Command
{
    protected $signature = 'attendance:sync-users {--machine-id= : Specific machine ID to sync}';
    protected $description = 'Sync users to attendance machines';

    protected $syncService;

    public function __construct(AttendanceSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        $machineId = $this->option('machine-id');
        
        if ($machineId) {
            $this->syncSpecificMachine($machineId);
        } else {
            $this->syncAllMachines();
        }
        
        return 0;
    }
    
    private function syncSpecificMachine($machineId)
    {
        $this->info("Syncing users to machine ID: {$machineId}");
        
        try {
            $result = $this->syncService->syncAllUsersToMachine($machineId);
            
            $this->info("✓ Synced {$result['synced']} users");
            if ($result['errors'] > 0) {
                $this->warn("⚠ {$result['errors']} errors occurred");
                foreach ($result['error_details'] as $error) {
                    $this->line("  - {$error}");
                }
            }
            
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
    
    private function syncAllMachines()
    {
        $machines = AttendanceMachine::where('is_active', true)->get();
        
        if ($machines->isEmpty()) {
            $this->warn('No active machines found.');
            return;
        }
        
        $this->info('Syncing users to all active machines...');
        
        foreach ($machines as $machine) {
            $this->info("Syncing to: {$machine->name}");
            
            try {
                $result = $this->syncService->syncAllUsersToMachine($machine->id);
                
                $this->info("  ✓ Synced {$result['synced']} users");
                if ($result['errors'] > 0) {
                    $this->warn("  ⚠ {$result['errors']} errors occurred");
                }
                
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                Log::error('User sync failed for machine', [
                    'machine_id' => $machine->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info('User synchronization completed.');
    }
}