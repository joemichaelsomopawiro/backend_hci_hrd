<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\EditorWork;
use App\Models\ProduksiWork;
use App\Models\SoundEngineerEditing;
use App\Models\Notification;
use App\Models\QualityControlWork;
use App\Models\PromotionWork;
use App\Models\ProductionTeam;
use App\Models\ShootingRunSheet;
use App\Http\Controllers\Api\EditorController;
use App\Http\Controllers\Api\ProducerController;
use App\Http\Controllers\Api\ProduksiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VerifyPhase6PostProduction extends Command
{
    protected $signature = 'verify:phase6-post-production {--cleanup : Delete the created data after run}';
    protected $description = 'Verify Phase 6: Editing & Post-Production Workflow';

    public function handle()
    {
        $this->info("🚀 Starting Phase 6 Verification: Editing & Post-Production...");
        $uniqueId = uniqid();

        try {
            // 1. Setup Data
            $this->info("\n[1] Setting up Data (Real DB, No Transaction)...");
            
            $producer = User::where('role', 'Producer')->first() ?? User::create([
                'name' => 'Verify Producer ' . $uniqueId,
                'email' => "producer6_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Producer'
            ]);
            
            $editor = User::where('role', 'Editor')->first() ?? User::create([
                'name' => 'Verify Editor ' . $uniqueId,
                'email' => "editor6_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Editor'
            ]);
            
            $produksiUser = User::where('role', 'Production')->first() ?? User::create([
                'name' => 'Verify Production ' . $uniqueId,
                'email' => "prod6_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Production'
            ]);

            $pm = User::where('role', 'Program Manager')->first() ?? User::create([
                'name' => 'Verify PM ' . $uniqueId,
                'email' => "pm6_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Program Manager'
            ]);

            $team = ProductionTeam::create([
                'name' => 'Team Phase 6 ' . $uniqueId,
                'producer_id' => $producer->id,
                'created_by' => $pm->id,
                'is_active' => 1
            ]);
            
            $program = Program::create([
                'name' => 'Phase 6 Program ' . $uniqueId,
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
                'title' => 'Phase 6 Integration Test',
                'status' => 'in_production',
                'air_date' => now()->addDays(7),
                'production_date' => now()->addDays(2),
                'production_deadline' => now()->addDays(5),
                'current_workflow_state' => 'editing',
                'production_team_id' => $team->id
            ]);

            // Create Run Sheet first
            $runSheet = ShootingRunSheet::create([
                'episode_id' => $episode->id,
                'shooting_date' => now()->format('Y-m-d'),
                'location' => 'Studio 1',
                'crew_list' => ['Cameraman' => 'John Doe', 'Director' => 'Jane Doe'],
                'equipment_list' => ['Camera' => 'Sony A7S III', 'Mic' => 'Sennheiser'],
                'status' => 'completed',
                'created_by' => $produksiUser->id
            ]);

            // Create initial ProduksiWork (Incomplete link for now)
            $productionWork = ProduksiWork::create([
                'episode_id' => $episode->id,
                'status' => 'completed',
                'created_by' => $produksiUser->id,
                'shooting_file_links' => 'https://storage.example.com/raw/initial_video.mp4',
                'run_sheet_id' => $runSheet->id
            ]);

            // Create initial EditorWork
            $editorWork = EditorWork::create([
                'episode_id' => $episode->id,
                'work_type' => 'main_episode',
                'status' => 'draft',
                'created_by' => $editor->id,
                'file_complete' => 0
            ]);

            $this->info("   ✅ Data created. Episode ID: {$episode->id}, Editor Work ID: {$editorWork->id}");

            // 2. Editor Accepts Work
            $this->info("\n[2] Editor Accepts Work...");
            $this->actingAs($editor);
            $editorController = app(EditorController::class);
            $resAccept = $editorController->acceptWork(new Request(), $editorWork->id);
            if ($resAccept->getData()->success) {
                $this->info("   ✅ Editor accepted the work. Status: " . $editorWork->fresh()->status);
            } else {
                $this->error("   ❌ Failed to accept work.");
                return 1;
            }

            // 3. Editor Checks Completeness (Should FAIL because audio is missing)
            $this->info("\n[3] Editor Checks File Completeness (Initial)...");
            $resCheck = $editorController->checkFileCompleteness(new Request(), $editorWork->id);
            $dataCheck = $resCheck->getData()->data;

            if (!$dataCheck->file_complete) {
                $this->info("   ✅ Check correctly identified missing files: " . implode(', ', $dataCheck->missing_files));
            } else {
                $this->error("   ❌ Check incorrectly identified files as complete.");
                return 1;
            }

            // 4. Editor Reports Missing Files to Producer
            $this->info("\n[4] Editor Reports Missing Files to Producer...");
            $resReport = $editorController->reportMissingFiles(new Request([
                'missing_files' => [
                    ['file_type' => 'audio', 'description' => 'Approved audio missing'],
                    ['file_type' => 'video', 'description' => 'B-Roll shots missing']
                ],
                'notes' => 'Please provide the missing files.'
            ]), $editorWork->id);

            if ($resReport->getData()->success) {
                $this->info("   ✅ Missing files reported successfully.");
            } else {
                $this->error("   ❌ Failed to report missing files.");
                return 1;
            }

            // 5. Producer Reviews and Requests Fix from Produksi
            $this->info("\n[5] Producer Reviews Report and Requests Fix from Produksi...");
            $this->actingAs($producer);
            $producerController = app(ProducerController::class);

            $resRequestAction = $producerController->requestProduksiAction(new Request([
                'produksi_work_id' => $productionWork->id,
                'request_type' => 'complete_files',
                'reason' => 'Editor needs more B-Roll.',
                'missing_files' => [['file_type' => 'video', 'description' => 'B-Roll shots']],
                'editor_work_id' => $editorWork->id
            ]));

            if ($resRequestAction->getData()->success) {
                $this->info("   ✅ Producer sent fix request.");
            } else {
                $this->error("   ❌ Producer failed to send fix request.");
                return 1;
            }

            // 6. Produksi Accepts Request
            $this->info("\n[6] Produksi Accepts Producer Request...");
            $this->actingAs($produksiUser);
            $produksiController = app(ProduksiController::class);

            $resProdReqs = $produksiController->getProducerRequests(new Request());
            $prodReqs = $resProdReqs->getData()->data;
            $targetReq = collect($prodReqs)->firstWhere('produksi_work_id', $productionWork->id);

            if ($targetReq) {
                $produksiController->acceptProducerRequest(new Request([
                    'request_id' => $targetReq->request_id,
                    'action' => 'accept',
                    'notes' => 'Will be done.'
                ]), $productionWork->id);
                $this->info("   ✅ Produksi accepted the request.");
            }

            // 7. Simulation of SE and Produksi completing their work
            $this->info("\n[7] Simulating SE Audio Submission & Produksi File Update...");
            
            $se = User::where('role', 'Sound Engineer')->first() ?? User::create([
                'name' => 'Verify SE',
                'email' => "se6_{$uniqueId}@example.com",
                'role' => 'Sound Engineer',
                'password' => bcrypt('password')
            ]);

            SoundEngineerEditing::updateOrCreate(
                ['episode_id' => $episode->id],
                [
                    'status' => 'approved',
                    'final_file_link' => 'https://storage.example.com/audio/final_audio.mp3',
                    'sound_engineer_id' => $se->id,
                    'created_by' => $se->id
                ]
            );

            // Important: refresh to get latest status after controller calls
            $productionWork->refresh();
            $this->info("      Before final update: ProduksiWork Status=" . $productionWork->status);
            
            $productionWork->update([
                'status' => 'completed',
                'shooting_file_links' => 'https://storage.example.com/raw/initial_video.mp4,https://storage.example.com/raw/b_roll.mp4'
            ]);
            $this->info("      After final update: ProduksiWork Status=" . $productionWork->fresh()->status);
            $this->info("   ✅ Audio submitted and production files updated.");

            // 8. Editor Checks Completeness Again (Should PASS now)
            $this->info("\n[8] Editor Checks File Completeness Again...");
            $this->actingAs($editor);
            
            // Debugging DB state before check
            $pWork = ProduksiWork::where('episode_id', $episode->id)->get();
            $this->info("      Diagnostic: Found " . $pWork->count() . " ProduksiWorks for episode.");
            foreach ($pWork as $pw) {
                $this->info("      - ID: {$pw->id}, Status: {$pw->status}, Links: " . ($pw->shooting_file_links ? 'YES' : 'NO'));
            }
            
            $aWork = SoundEngineerEditing::where('episode_id', $episode->id)->where('status', 'approved')->first();
            $this->info("      Diagnostic: Audio Status=" . ($aWork->status ?? 'MISSING') . ", Link=" . ($aWork->final_file_link ?? 'EMPTY'));

            $resCheck2 = $editorController->checkFileCompleteness(new Request(), $editorWork->id);
            $dataCheck2 = $resCheck2->getData()->data;

            if ($dataCheck2->file_complete) {
                $this->info("   ✅ Files are now complete. Status updated to 'editing'.");
            } else {
                $this->error("   ❌ Files still marked as incomplete.");
                $this->info("      Response: " . json_encode($dataCheck2));
                return 1;
            }

            // 9. Editor Accesses Run Sheet
            $this->info("\n[9] Editor Accesses Run Sheet...");
            $resRunSheet = $editorController->getRunSheet(new Request(), $episode->id);
            if ($resRunSheet->getData()->success) {
                $this->info("   ✅ Run sheet retrieved: " . $resRunSheet->getData()->data->run_sheet->location);
            } else {
                $this->error("   ❌ Failed to retrieve run sheet.");
                return 1;
            }

            // 10. Editor Inputs Final Links
            $this->info("\n[10] Editor Inputs Final Edited Links...");
            $resLinks = $editorController->inputFileLinks(new Request([
                'file_links' => [
                    ['url' => 'https://storage.example.com/final/EP_FINAL_V1.mp4', 'file_name' => 'Main Episode Final'],
                    ['url' => 'https://storage.example.com/final/EP_BTS.mp4', 'file_name' => 'BTS Clip']
                ]
            ]), $editorWork->id);

            if ($resLinks->getData()->success) {
                $this->info("   ✅ Final links added successfully.");
                $this->info("      EditorWork file_path: " . $editorWork->fresh()->file_path);
            } else {
                $this->error("   ❌ Failed to add final links.");
                return 1;
            }

            // 11. Editor Submits Work
            $this->info("\n[11] Editor Submits Final Work...");
            $resSubmit = $editorController->submit(new Request([
                'submission_notes' => 'Editing complete. Checked with run sheet.'
            ]), $editorWork->id);

            if ($resSubmit->getData()->success) {
                $this->info("   ✅ Editor submitted work successfully.");
            } else {
                $this->error("   ❌ Failed to submit work: " . $resSubmit->getData()->message);
                return 1;
            }

            // 12. Final Verification of Downstream Tasks
            $this->info("\n[12] Verifying Downstream QC and Promotion Tasks...");
            $qcExists = QualityControlWork::where('episode_id', $episode->id)->exists();
            $promoExists = PromotionWork::where('episode_id', $episode->id)->exists();

            if ($qcExists && $promoExists) {
                $this->info("   ✅ QualityControlWork and PromotionWork successfully auto-created.");
            } else {
                $this->error("   ❌ Downstream tasks were not created correctly.");
                if (!$qcExists) $this->error("      - QualityControlWork MISSING");
                if (!$promoExists) $this->error("      - PromotionWork MISSING");
            }

            if ($this->option('cleanup')) {
                $this->info("\n[Cleanup] Deleting created data...");
                $episode->delete();
                $program->delete();
                $team->delete();
                $this->info("   ✅ Cleanup done.");
            }

            $this->info("\n🎉 Phase 6 Verification Completed Successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Phase 6 Verification Failed!");
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
