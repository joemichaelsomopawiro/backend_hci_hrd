<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CreativeWork;
use Illuminate\Support\Facades\Log;

class FixAutoApprovedCreativeWorks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:auto-approved-creative-works';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix creative works that were auto-approved without required data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for auto-approved creative works without required data...');

        // Get all approved creative works
        $approvedWorks = CreativeWork::where('status', 'approved')
            ->with(['episode'])
            ->get();

        $this->info("Found {$approvedWorks->count()} approved Creative Works");

        $fixedCount = 0;
        $skippedCount = 0;

        foreach ($approvedWorks as $creativeWork) {
            $this->line("Checking Creative Work ID: {$creativeWork->id} (Episode: {$creativeWork->episode_id})");

            // Check if creative work is missing required data
            $missingData = [];
            
            if (empty($creativeWork->script_content) || trim($creativeWork->script_content) === '') {
                $missingData[] = 'Script content';
            }
            
            if (empty($creativeWork->storyboard_data) || (is_array($creativeWork->storyboard_data) && count($creativeWork->storyboard_data) === 0)) {
                $missingData[] = 'Storyboard data';
            }
            
            if (empty($creativeWork->budget_data) || (is_array($creativeWork->budget_data) && count($creativeWork->budget_data) === 0)) {
                $missingData[] = 'Budget data';
            }
            
            if (empty($creativeWork->recording_schedule)) {
                $missingData[] = 'Recording schedule';
            }
            
            if (empty($creativeWork->shooting_schedule)) {
                $missingData[] = 'Shooting schedule';
            }

            if (!empty($missingData)) {
                $this->warn("  ⚠️  Missing data: " . implode(', ', $missingData));
                
                // Revert status to 'draft' so Creative can complete the work
                $creativeWork->update([
                    'status' => 'draft',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => 'Auto-reverted: Missing required data. Please complete all required fields and submit again.',
                    'script_approved' => null,
                    'storyboard_approved' => null,
                    'budget_approved' => null,
                ]);

                $this->info("  ✅ Status reverted to 'draft'");
                $fixedCount++;

                Log::info('FixAutoApprovedCreativeWorks - Creative Work reverted', [
                    'creative_work_id' => $creativeWork->id,
                    'episode_id' => $creativeWork->episode_id,
                    'missing_data' => $missingData,
                    'old_status' => 'approved',
                    'new_status' => 'draft'
                ]);
            } else {
                $this->info("  ✅ All required data present");
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Fixed (reverted to draft): {$fixedCount}");
        $this->info("  - Skipped (data complete): {$skippedCount}");
        $this->info("  - Total checked: {$approvedWorks->count()}");

        if ($fixedCount > 0) {
            $this->newLine();
            $this->warn("⚠️  {$fixedCount} creative work(s) have been reverted to 'draft' status.");
            $this->warn("   Creative users need to complete the missing data and submit again.");
        }

        return Command::SUCCESS;
    }
}

