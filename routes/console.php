<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Services\PrProgramService;
use App\Services\PrWorkflowService;
use App\Services\PrActivityLogService;
use App\Constants\Role;
use Illuminate\Support\Facades\Auth;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('verify:history', function () {
    $this->info("Starting History Verification...");

    try {
        $manager = User::where('role', 'program_manager')->first();
        if (!$manager) {
            $manager = User::first();
            if (!$manager) {
                $this->error("No users found.");
                return;
            }
        }

        Auth::login($manager);

        // 1. Create Program
        $this->info("Creating test program...");
        $programService = app(PrProgramService::class);
        $program = $programService->createProgram([
            'name' => 'History Test Program ' . time(),
            'description' => 'Test Description',
            'start_date' => now()->format('Y-m-d'),
            'air_time' => '10:00',
            'program_year' => date('Y')
        ], $manager->id);

        $this->info("Program created: {$program->name}");

        // Verify Log 1: Create
        $logService = app(PrActivityLogService::class);
        $logs = $logService->getProgramHistory($program->id);
        $this->info("Logs count after create: " . $logs->count());

        foreach ($logs as $log) {
            $this->info("Log: {$log->action} - {$log->description}");
        }

        // 2. Start Workflow Step
        $episode = $program->episodes()->first();
        $workflowService = app(PrWorkflowService::class);

        $this->info("Starting Step 1...");
        $workflowService->startStep($episode->id, 1, $manager->id);

        // Verify Log 2: Start Step
        $logs = $logService->getProgramHistory($program->id);
        $this->info("Logs count after start step: " . $logs->count());

        $found = false;
        foreach ($logs as $log) {
            $this->info("Log: {$log->action} - {$log->description}");
            if ($log->action === 'start_step')
                $found = true;
        }

        if ($found) {
            $this->info("VERIFICATION SUCCESSFUL!");
        } else {
            $this->error("Verification Failed: 'start_step' not found");
        }

        // Cleanup
        $this->info("Cleaning up...");
        $program->episodes()->each(function ($ep) {
            $ep->workflowProgress()->delete();
            $ep->forceDelete();
        });
        $program->forceDelete();
        \App\Models\PrActivityLog::where('program_id', $program->id)->delete();

    } catch (\Exception $e) {
        $this->error("Error: " . $e->getMessage());
        if (isset($program)) {
            try {
                $program->forceDelete();
                \App\Models\PrActivityLog::where('program_id', $program->id)->delete();
            } catch (\Exception $x) {
            }
        }
    }
});
