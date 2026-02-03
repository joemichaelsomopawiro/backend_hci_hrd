<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\CreativeWork;
use App\Models\Budget;
use App\Models\BudgetRequest;
use App\Models\ProductionTeam;
use App\Models\ProductionEquipment;
use App\Models\SoundEngineerRecording;
use App\Models\SoundEngineerEditing;
use App\Models\ProduksiWork;
use App\Models\PromotionWork;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\GeneralAffairsController;
use App\Http\Controllers\Api\SoundEngineerController;
use App\Http\Controllers\Api\SoundEngineerEditingController;
use App\Http\Controllers\Api\ProduksiController;
use App\Http\Controllers\Api\PromosiController;
use App\Http\Controllers\Api\ArtSetPropertiController;
use Illuminate\Support\Facades\Auth;

class VerifyPhase5Execution extends Command
{
    protected $signature = 'verify:phase5-execution';
    protected $description = 'Verify Phase 5: Executing Departments (GA, SE, Produksi, Promosi)';

    public function handle()
    {
        $this->info("🚀 STARTING PHASE 5 VERIFICATION: EXECUTING DEPARTMENTS");
        $this->info("=======================================================");

        // 1. Setup Environment
        $this->info("\n[1] Setting up Environment...");
        
        $manager = User::firstOrCreate(['email' => 'manager_p5@test.com'], ['name' => 'Manager P5', 'role' => 'Program Manager', 'phone' => '08500000001', 'password' => bcrypt('password')]);
        $producer = User::firstOrCreate(['email' => 'producer_p5@test.com'], ['name' => 'Producer P5', 'role' => 'Producer', 'phone' => '08500000002', 'password' => bcrypt('password')]);
        $ga = User::firstOrCreate(['email' => 'ga_p5@test.com'], ['name' => 'GA P5', 'role' => 'General Affairs', 'phone' => '08500000003', 'password' => bcrypt('password')]);
        $se = User::firstOrCreate(['email' => 'se_p5@test.com'], ['name' => 'SE P5', 'role' => 'Sound Engineer', 'phone' => '08500000004', 'password' => bcrypt('password')]);
        $produksi = User::firstOrCreate(['email' => 'prod_p5@test.com'], ['name' => 'Produksi P5', 'role' => 'Production', 'phone' => '08500000005', 'password' => bcrypt('password')]);
        $promosi = User::firstOrCreate(['email' => 'promo_p5@test.com'], ['name' => 'Promosi P5', 'role' => 'Promotion', 'phone' => '08500000006', 'password' => bcrypt('password')]);
        $art = User::firstOrCreate(['email' => 'art_p5@test.com'], ['name' => 'Art P5', 'role' => 'Art & Set Properti', 'phone' => '08500000007', 'password' => bcrypt('password')]);

        $team = ProductionTeam::firstOrCreate(
            ['name' => 'Team P5 Verify'],
            ['manager_program_id' => $manager->id, 'producer_id' => $producer->id, 'created_by' => $manager->id]
        );

        // Assign users to team
        $team->members()->firstOrCreate(['user_id' => $se->id], ['role' => 'sound_eng']);
        $team->members()->firstOrCreate(['user_id' => $produksi->id], ['role' => 'production']);
        $team->members()->firstOrCreate(['user_id' => $promosi->id], ['role' => 'creative']); // Assuming Promosi is mapped or handled separately

        $program = Program::firstOrCreate(
            ['name' => 'Execution Test Program'],
            [
                'status' => 'active', 
                'category' => 'musik', 
                'manager_program_id' => $manager->id, 
                'production_team_id' => $team->id,
                'start_date' => now(),
                'air_time' => '19:00:00'
            ]
        );

        $episode = Episode::firstOrCreate(
            ['program_id' => $program->id, 'episode_number' => 501],
            ['title' => 'Exec Test Ep', 'status' => 'in_production', 'rundown' => 'Rundown', 'air_date' => now()->addDays(7)]
        );

        // Simulate Approved Creative Work with Budget & Schedule
        $work = CreativeWork::firstOrCreate(
            ['episode_id' => $episode->id],
            [
                'status' => 'approved',
                'created_by' => $manager->id, // Simplified
                'recording_schedule' => now()->addDay(),
                'shooting_schedule' => now()->addDays(2),
                'total_budget' => 5000000,
                'budget_data' => [
                    ['category' => 'Talent', 'amount' => 5000000, 'description' => 'Main Artist']
                ]
            ]
        );

        // Detect if Budget objects exist (Refined Phase 4) or if we need to manually create BudgetRequest
        $budget = Budget::firstOrCreate(
            ['episode_id' => $episode->id, 'budget_type' => 'talent_fee'],
            ['amount' => 5000000, 'description' => 'Talent Fee', 'status' => 'approved', 'requested_by' => $producer->id]
        );
        
        // Ensure BudgetRequest exists (triggers GA)
        // Usually triggerPostCreativeApproval creates this. We simulate it here.
        $budgetReq = BudgetRequest::firstOrCreate(
            ['program_id' => $program->id, 'request_type' => 'creative_work'],
            [
                'requested_by' => $producer->id,
                'title' => 'Budget Req for Ep 501',
                'description' => 'Funds',
                'requested_amount' => 5000000,
                'status' => 'pending'
            ]
        );

        $this->info("   ✅ Setup Complete.");

        // 2. GA Workflow
        $this->info("\n[2] GA Processes Budget Request...");
        $this->actingAs($ga);
        $gaController = app(GeneralAffairsController::class);
        
        // Approve Budget
        $this->info("   [2.1] Approving Budget Request...");
        $resApprove = $gaController->approve(new Request([
            'approved_amount' => 5000000,
            'approval_notes' => 'Budget approved for Ep 501',
            'payment_method' => 'Transfer',
            'payment_schedule' => now()->format('Y-m-d')
        ]), $budgetReq->id);

        $budgetReq->refresh();
        if ($budgetReq->status === 'approved') {
            $this->info("   ✅ Budget Approved.");
        } else {
            $this->error("   ❌ Budget Approval Failed. Status: {$budgetReq->status}");
            if (isset($resApprove)) {
                $this->error("      Response: " . json_encode($resApprove->getData()));
            }
        }

        // Process Payment (Disbursement)
        $this->info("   [2.2] Processing Payment (Disbursement)...");
        $resPay = $gaController->processPayment(new Request([
            'payment_receipt' => 'RC-501-ABC',
            'payment_notes' => 'Funds transferred to Production Account',
            'payment_date' => now()->format('Y-m-d')
        ]), $budgetReq->id);

        $budgetReq->refresh();
        if ($budgetReq->status === 'paid') {
            $this->info("   ✅ Budget Paid (Disbursed).");
        } else {
            $this->error("   ❌ Payment Processing Failed. Status: {$budgetReq->status}");
            if (isset($resPay)) {
                $this->error("      Response: " . json_encode($resPay->getData()));
            }
        }

        // 3. Sound Engineer Workflow
        $this->info("\n[3] Sound Engineer Workflow (Recording & Editing)...");
        $this->actingAs($se);
        $seController = app(SoundEngineerController::class);
        
        // 3.1 Equipment Request
        $this->info("   [3.1] Requesting Equipment...");
        // This usually creates ProducitonEquipment or EquipmentRequest
        // $seController->requestEquipment(...)

        // 3.2 Submit Recording (Link)
        $this->info("   [3.2] Submitting Recording (Link)...");
        // Ensure Recording Task exists and is in 'recording' status
        $recordingTask = SoundEngineerRecording::firstOrCreate(
            ['episode_id' => $episode->id],
            ['sound_engineer_id' => $se->id, 'created_by' => $manager->id]
        );
        $recordingTask->update(['status' => 'recording']);

        $resRec = $seController->completeRecording(new Request([
            'vocal_file_link' => 'https://drive.google.com/vocal_recording',
            'notes' => 'Vocal Done'
        ]), $recordingTask->id);

        $recordingTask->refresh();
        if ($recordingTask->status === 'completed' && $recordingTask->file_link === 'https://drive.google.com/vocal_recording') {
            $this->info("   ✅ Recording Submitted with Link.");
        } else {
             // Maybe API failure
             $this->error("   ❌ Recording Submission Failed. Status: {$recordingTask->status}");
             if (isset($resRec)) {
                 $this->error("      Response: " . json_encode($resRec->getData()));
             }
        }

        // 3.3 Vocal Editing Link
        $this->info("   [3.3] Submitting Edited Vocal (Link)...");
        $editController = app(SoundEngineerEditingController::class);
        
        // Ensure Editing Task exists and is in 'in_progress' status
        $editingTask = SoundEngineerEditing::firstOrCreate(
            ['episode_id' => $episode->id],
            ['sound_engineer_id' => $se->id, 'created_by' => $manager->id]
        );
        $editingTask->update(['status' => 'in_progress']);

        $resEdit = $editController->submit(new Request([
            'final_file_link' => 'https://drive.google.com/final_mix',
            'notes' => 'Mix Done'
        ]), $editingTask->id);

         $editingTask->refresh();
        if ($editingTask->status === 'submitted' && $editingTask->final_file_link === 'https://drive.google.com/final_mix') {
            $this->info("   ✅ Editing Submitted for QC with Link.");
        } else {
             $this->error("   ❌ Editing Submission Failed. Status: {$editingTask->status}");
        }

        // 4. Produksi Workflow (Shooting)
        $this->info("\n[4] Produksi Workflow (Shooting)...");
        $this->actingAs($produksi);
        $prodController = app(ProduksiController::class);
        
        // Ensure Work exists
        $prodWork = ProduksiWork::firstOrCreate(
            ['episode_id' => $episode->id],
            ['created_by' => $produksi->id, 'status' => 'pending']
        );
        $prodWork->update(['status' => 'in_progress']);

        $this->info("   [4.1] Submitting Shooting Result (Link)...");
        
        // Input Links first - Correct structure
        $prodController->inputFileLinks(new Request([
            'file_links' => [
                [
                    'url' => 'https://drive.google.com/shooting_rushes',
                    'file_name' => 'Shooting Rushes',
                    'file_size' => 1024000,
                    'mime_type' => 'video/mp4'
                ]
            ]
        ]), $prodWork->id);

        $resShoot = $prodController->completeWork(new Request([
            'notes' => 'All shots done'
        ]), $prodWork->id);

        $prodWork->refresh();
        // Producing Controller usually updates status to 'completed'
        // Check if links are saved (json string or array)
        $linksString = $prodWork->shooting_file_links ?? '';
        $hasLinks = str_contains($linksString, 'https://drive.google.com/shooting_rushes');
        
        if ($prodWork->status === 'completed' && $hasLinks) {
             $this->info("   ✅ Shooting Submitted with Link.");
        } else {
             $this->error("   ❌ Shooting Submission Failed. Status: {$prodWork->status}");
             if (isset($resShoot)) {
                 $this->error("      Response: " . json_encode($resShoot->getData()));
             }
        }

        // 5. Promosi Workflow
        $this->info("\n[5] Promosi Workflow (Content)...");
        $this->actingAs($promosi);
        $promoController = app(PromosiController::class);

        // Ensure Work Exists
        $promoWork = PromotionWork::firstOrCreate(
            ['episode_id' => $episode->id, 'work_type' => 'bts_video'],
            ['created_by' => $promosi->id, 'status' => 'planning', 'title' => 'BTS Video Verify']
        );
        $promoWork->update(['status' => 'shooting']); // Update to active state

        $this->info("   [5.1] Submitting BTS Content (Links)...");
        
        // 1. Upload BTS Video
        $promoController->uploadBTSVideo(new Request([
            'file_link' => 'https://drive.google.com/bts_video'
        ]), $promoWork->id);

        // 2. Upload Talent Photos
        $promoController->uploadTalentPhotos(new Request([
            'file_links' => ['https://drive.google.com/talent_photo_1', 'https://drive.google.com/talent_photo_2']
        ]), $promoWork->id);

        // 3. Complete Work
        $resPromo = $promoController->completeWork(new Request([
            'completion_notes' => 'BTS Ready'
        ]), $promoWork->id);

        $promoWork->refresh();
        if (in_array($promoWork->status, ['completed', 'editing', 'review', 'approved'])) {
             $this->info("   ✅ BTS Submitted/Completed.");
        } else {
             $this->error("   ❌ BTS Submission Failed. Status: {$promoWork->status}");
             if (isset($resPromo)) {
                 $this->error("      Response: " . json_encode($resPromo->getData()));
             }
        }
        
        $this->info("\n✅ PHASE 5 VERIFICATION FINISHED");
    }


    private function actingAs($user)
    {
        Auth::login($user);
    }
}
