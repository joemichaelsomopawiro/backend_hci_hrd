<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\EditorWork;
use App\Models\PromotionWork;
use App\Models\DesignGrafisWork;
use App\Models\QualityControlWork;
use App\Models\BroadcastingWork;
use App\Models\ProductionTeam;
use App\Http\Controllers\Api\QualityControlController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyPhase7QualityControl extends Command
{
    protected $signature = 'verify:phase7-quality-control {--cleanup : Delete the created data after run}';
    protected $description = 'Verify Phase 7: Quality Control Workflow';

    public function handle()
    {
        $this->info("🚀 Starting Phase 7 Verification: Quality Control...");
        $uniqueId = uniqid();

        try {
            // 1. Setup Data
            $this->info("\n[1] Setting up Data...");
            
            $producer = User::where('role', 'Producer')->first() ?? User::create([
                'name' => 'Verify Producer ' . $uniqueId,
                'email' => "producer7_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Producer'
            ]);
            
            $qcUser = User::where('role', 'Quality Control')->first() ?? User::create([
                'name' => 'Verify QC ' . $uniqueId,
                'email' => "qc7_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Quality Control'
            ]);
            
            $editor = User::where('role', 'Editor')->first() ?? User::create([
                'name' => 'Verify Editor ' . $uniqueId,
                'email' => "editor7_{$uniqueId}@example.com",
                'role' => 'Editor',
                'password' => bcrypt('password')
            ]);

            $pm = User::where('role', 'Program Manager')->first() ?? User::create([
                'name' => 'Verify PM ' . $uniqueId,
                'email' => "pm7_{$uniqueId}@example.com",
                'role' => 'Program Manager',
                'password' => bcrypt('password')
            ]);

            $team = ProductionTeam::create([
                'name' => 'Team Phase 7 ' . $uniqueId,
                'producer_id' => $producer->id,
                'created_by' => $pm->id,
                'is_active' => 1
            ]);
            
            $program = Program::create([
                'name' => 'Phase 7 Program ' . $uniqueId,
                'category' => 'live_tv',
                'status' => 'active',
                'start_date' => now()->format('Y-m-d'),
                'air_time' => '20:00:00',
                'manager_program_id' => $pm->id,
                'production_team_id' => $team->id
            ]);

            $episode = Episode::create([
                'program_id' => $program->id,
                'episode_number' => rand(1000, 9999),
                'title' => 'Phase 7 QC Integration Test',
                'status' => 'in_production',
                'air_date' => now()->addDays(7),
                'production_date' => now()->addDays(2),
                'production_deadline' => now()->addDays(5),
                'current_workflow_state' => 'quality_control',
                'production_team_id' => $team->id
            ]);

            // Create Submissions
            $editorWork = EditorWork::create([
                'episode_id' => $episode->id,
                'work_type' => 'main_episode',
                'status' => 'completed',
                'file_link' => 'https://storage.example.com/final/video.mp4',
                'created_by' => $editor->id
            ]);

            // QC Work record
            $qcWork = QualityControlWork::create([
                'episode_id' => $episode->id,
                'created_by' => $producer->id,
                'title' => 'QC review for ' . $episode->title,
                'files_to_check' => [
                    ['file_path' => $editorWork->file_link, 'editor_work_id' => $editorWork->id, 'type' => 'main_video']
                ],
                'status' => 'pending'
            ]);

            $this->info("   ✅ Data created. Episode ID: {$episode->id}, QC Work ID: {$qcWork->id}");

            // 2. QC Accepts Work
            $this->info("\n[2] QC Accepts Work...");
            $this->actingAs($qcUser);
            $qcController = app(QualityControlController::class);
            $resAccept = $qcController->acceptWork(new Request(), $qcWork->id);
            if ($resAccept->getData()->success) {
                $this->info("   ✅ QC accepted the work. Status: " . $qcWork->fresh()->status);
            } else {
                $this->error("   ❌ Failed to accept work.");
                return 1;
            }

            // 3. QC Submits Form
            $this->info("\n[3] QC Submits Form...");
            $resForm = $qcController->submitQCFormForWork(new Request([
                'qc_notes' => 'Great video quality, but some audio spikes.',
                'quality_score' => 85,
                'issues_found' => ['Audio spikes in segment 2'],
                'improvements_needed' => ['Normalize audio'],
                'qc_checklist' => ['resolution' => true, 'duration' => true, 'audio' => false]
            ]), $qcWork->id);

            if ($resForm->getData()->success) {
                $this->info("   ✅ QC form submitted. Status: " . $qcWork->fresh()->status);
            } else {
                $this->error("   ❌ Failed to submit QC form: " . ($resForm->getData()->message ?? 'Unknown error'));
                if (isset($resForm->getData()->errors)) {
                    $this->error("      Errors: " . json_encode($resForm->getData()->errors));
                }
                return 1;
            }

            // 4. QC Finalize -> Reject
            $this->info("\n[4] QC Rejects for Revision...");
            $resReject = $qcController->finalize(new Request([
                'action' => 'reject',
                'notes' => 'Please fix audio spikes in segment 2.'
            ]), $qcWork->id);

            if ($resReject->getData()->success) {
                $this->info("   ✅ QC rejected successfully.");
                $this->info("      EditorWork status: " . $editorWork->fresh()->status);
                if ($editorWork->fresh()->status === 'rejected') {
                    $this->info("   ✅ EditorWork correctly rolled back to 'rejected'.");
                } else {
                    $this->warning("   ⚠️ EditorWork status is " . $editorWork->fresh()->status . ", expected 'rejected'.");
                }
            } else {
                $this->error("   ❌ Failed to reject QC: " . ($resReject->getData()->message ?? 'Unknown error'));
                return 1;
            }

            // 5. Simulate Revision Fix
            $this->info("\n[5] Simulating Editor Revision Fix...");
            $editorWork->update(['status' => 'completed', 'file_link' => 'https://storage.example.com/final/video_v2.mp4']);
            $qcWork->update(['status' => 'completed']); // Set back to completed for re-finalization
            $this->info("   ✅ Editor resubmitted.");

            // 6. QC Finalize -> Approve
            $this->info("\n[6] QC Approves Final Work...");
            $resApprove = $qcController->finalize(new Request([
                'action' => 'approve',
                'notes' => 'Audio is perfect now. Approved.'
            ]), $qcWork->id);

            if ($resApprove->getData()->success) {
                $this->info("   ✅ QC approved successfully.");
            } else {
                $this->error("   ❌ Failed to approve QC: " . $resApprove->getData()->message);
                return 1;
            }

            // 7. Verify Downstream Broadcasting Task
            $this->info("\n[7] Verifying Downstream Broadcasting Task...");
            $broadcastingWork = BroadcastingWork::where('episode_id', $episode->id)->first();

            if ($broadcastingWork) {
                $this->info("   ✅ BroadcastingWork successfully created.");
                $this->info("      Video Path: " . $broadcastingWork->video_file_path);
            } else {
                $this->error("   ❌ BroadcastingWork NOT created.");
            }

            if ($this->option('cleanup')) {
                $this->info("\n[Cleanup] Deleting created data...");
                $episode->delete();
                $program->delete();
                $team->delete();
                $this->info("   ✅ Cleanup done.");
            }

            $this->info("\n🎉 Phase 7 Verification Completed Successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Phase 7 Verification Failed!");
            $this->error("      Error: " . $e->getMessage());
            $this->error("      Line: " . $e->getLine());
            return 1;
        }
    }

    private function actingAs($user)
    {
        Auth::login($user);
    }
}
