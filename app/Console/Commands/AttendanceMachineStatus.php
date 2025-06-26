<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceMachineService;
use App\Models\AttendanceMachine;
use Illuminate\Support\Facades\Log;

class AttendanceMachineStatus extends Command
{
    protected $signature = 'attendance:machine-status {--machine-id= : Specific machine ID to check}';
    protected $description = 'Check attendance machine status';

    protected $machineService;

    public function __construct(AttendanceMachineService $machineService)
    {
        parent::__construct();
        $this->machineService = $machineService;
    }

    public function handle()
    {
        $machineId = $this->option('machine-id');
        
        if ($machineId) {
            $this->checkSpecificMachine($machineId);
        } else {
            $this->checkAllMachines();
        }
        
        return 0;
    }
    
    private function checkSpecificMachine($machineId)
    {
        try {
            $machine = AttendanceMachine::findOrFail($machineId);
            $this->info("Checking machine: {$machine->name}");
            
            $result = $this->machineService->testConnection($machineId);
            
            if ($result['success']) {
                $this->info("✓ Machine is online and responsive");
                $machine->update(['last_ping_at' => now()]);
            } else {
                $this->error("✗ Machine is offline or not responding: {$result['message']}");
            }
            
        } catch (\Exception $e) {
            $this->error('Status check failed: ' . $e->getMessage());
            return 1;
        }
    }
    
    private function checkAllMachines()
    {
        $machines = AttendanceMachine::all();
        
        if ($machines->isEmpty()) {
            $this->warn('No machines configured.');
            return;
        }
        
        $this->info('Checking all attendance machines...');
        
        foreach ($machines as $machine) {
            $this->line("Checking: {$machine->name} ({$machine->ip_address})");
            
            try {
                $result = $this->machineService->testConnection($machine->id);
                
                if ($result['success']) {
                    $this->info("  ✓ Online");
                    $machine->update(['last_ping_at' => now()]);
                } else {
                    $this->error("  ✗ Offline: {$result['message']}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error('Machine status check failed', [
                    'machine_id' => $machine->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info('Status check completed.');
    }
}