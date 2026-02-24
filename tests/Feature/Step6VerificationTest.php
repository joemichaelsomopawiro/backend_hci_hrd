<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorPromosiWork;
use App\Services\PrWorkflowService;
use App\Models\PrEpisodeWorkflowProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Step6VerificationTest extends TestCase
{
    // We don't use RefreshDatabase trait to avoid wiping the existing DB if it's a shared dev env,
    // instead we manage our own cleanup.

    public function test_step_6_auto_completion()
    {
        // 1. Create User
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No user found');
        }

        // 2. Create Program
        $program = PrProgram::firstOrCreate(
            ['name' => 'Test Program Verification'],
            [
                'desc' => 'Test',
                'status' => 'active',
                'created_by' => $user->id,
                'manager_program_id' => $user->id
            ]
        );

        // 3. Create Episode
        $episode = PrEpisode::create([
            'pr_program_id' => $program->id,
            'episode_number' => rand(10000, 99999),
            'title' => 'Test Episode Step 6 Verification ' . rand(1, 1000),
            'status' => 'in_progress',
            'created_by' => $user->id
        ]);

        try {
            // 4. Initialize Workflow
            $service = app(PrWorkflowService::class);
            $service->initializeWorkflow($episode);

            // 5. Create sub-works
            // Editor: pending_qc
            PrEditorWork::create([
                'pr_episode_id' => $episode->id,
                'status' => 'pending_qc',
                'pr_production_work_id' => 1,
                'assigned_to' => $user->id,
                'files_complete' => false
            ]);

            // Promotion: completed
            PrPromotionWork::create([
                'pr_episode_id' => $episode->id,
                'status' => 'completed',
                'created_by' => $user->id,
                'work_type' => 'bts_video'
            ]);

            // Editor Promosi: pending_qc
            PrEditorPromosiWork::create([
                'pr_episode_id' => $episode->id,
                'status' => 'pending_qc',
                'assigned_to' => $user->id
            ]);

            // 6. Call getWorkflowVisualization
            $service->getWorkflowVisualization($episode->id);

            // 7. Assert
            $step6 = PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
                ->where('workflow_step', 6)
                ->first();

            $this->assertEquals('completed', $step6->status, 'Step 6 should be completed');

        } finally {
            // Cleanup
            if ($episode) {
                // Delete related works
                PrEditorWork::where('pr_episode_id', $episode->id)->delete();
                PrPromotionWork::where('pr_episode_id', $episode->id)->delete();
                PrEditorPromosiWork::where('pr_episode_id', $episode->id)->delete();
                $episode->workflowProgress()->delete();
                $episode->delete();
            }
        }
    }
}
