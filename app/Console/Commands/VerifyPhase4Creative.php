<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\ProductionTeam;
use App\Models\ProductionTeamMember;
use App\Models\ProductionTeamAssignment;
use App\Models\Episode;
use App\Models\CreativeWork;
use App\Models\ProgramApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\CreativeController;
use App\Http\Controllers\Api\ProducerController;
use Illuminate\Support\Facades\Auth;

class VerifyPhase4Creative extends Command
{
    protected $signature = 'verify:phase4-creative';
    protected $description = 'Verify Phase 4: Creative & Pre-Production (Links, Special Budget, Team Assignment, Edit Bypass)';

    public function handle()
    {
        $this->info("🚀 STARTING PHASE 4 VERIFICATION: CREATIVE REFINEMENT");
        $this->info("=====================================================");

        // 1. Setup Environment
        $this->info("\n[1] Setting up Environment...");
        
        $manager = User::firstOrCreate(
            ['email' => 'manager_p4@test.com'],
            ['name' => 'Manager P4', 'role' => 'Program Manager', 'phone' => '08100000044', 'password' => bcrypt('password')]
        );
        $producer = User::firstOrCreate(
            ['email' => 'producer_p4@test.com'],
            ['name' => 'Producer P4', 'role' => 'Producer', 'phone' => '08200000044', 'password' => bcrypt('password')]
        );
        $creative = User::firstOrCreate(
            ['email' => 'creative_p4@test.com'],
            ['name' => 'Creative P4', 'role' => 'Creative', 'phone' => '08300000044', 'password' => bcrypt('password')]
        );
        // Random users for ad-hoc team
        $cameraman = User::firstOrCreate(['email' => 'cam_p4@test.com'], ['name' => 'Cameraman P4', 'role' => 'Production', 'phone' => '08777777777', 'password' => bcrypt('password')]);
        $artcrew = User::firstOrCreate(['email' => 'art_p4@test.com'], ['name' => 'Art P4', 'role' => 'Art & Set Properti', 'phone' => '08888888888', 'password' => bcrypt('password')]);

        $team = ProductionTeam::firstOrCreate(
            ['name' => 'Team P4 Verify'],
            ['manager_program_id' => $manager->id, 'producer_id' => $producer->id, 'created_by' => $manager->id]
        );

        $program = Program::firstOrCreate(
            ['name' => 'Creative Test Program'],
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
            ['program_id' => $program->id, 'episode_number' => 401],
            ['title' => 'Creative Test Ep', 'status' => 'in_production', 'rundown' => 'Rundown', 'air_date' => now()->addDays(7)]
        );

        $this->info("   ✅ Environment Setup Complete.");

        // 2. Mock Creative Work Submission (Link & Normal Budget)
        $this->info("\n[2] Creative Submits Work (Links & Normal Budget)...");
        $this->actingAs($creative);

        $creativeController = app(CreativeController::class);
        
        // Cek if submitted work exists
        $work = CreativeWork::where('episode_id', $episode->id)->first();
        if ($work) {
            $work->delete(); // Reset for clean test
        }

        // Create Draft
        $reqCreate = new Request([
             'episode_id' => $episode->id,
             'script_content' => 'Initial Script',
             'script_link' => 'https://docs.google.com/script', 
             'storyboard_link' => 'https://canva.com/storyboard',
             'budget_data' => [
                 ['category' => 'Talent', 'amount' => 5000000, 'description' => 'Main Artist'],
                 ['category' => 'Consumption', 'amount' => 1000000, 'description' => 'Crew food']
             ],
             'created_by' => $creative->id
        ]);
        $resCreate = $creativeController->store($reqCreate);
        $workData = $resCreate->getData(true)['data'];
        $workId = $workData['id'];

        // Accept Work (Draft -> In Progress)
        $resAccept = $creativeController->acceptWork(new Request(), $workId);
        if (!$resAccept->getData(true)['success']) {
             $this->error("   ❌ Accept Work Failed: " . $resAccept->getData(true)['message']);
             return;
        }
        $this->info("   ✅ Work Accepted (Draft -> In Progress).");

        // Submit
        $resComplete = $creativeController->completeWork(new Request([
            'script_link' => 'https://docs.google.com/script-final',
            'storyboard_link' => 'https://canva.com/storyboard-final',
            'budget_data' => $reqCreate->budget_data, // Same budget
            'completion_notes' => 'Ready for review'
        ]), $workId);
        
        $work = CreativeWork::find($workId);
        if ($work->status === 'submitted' && $work->script_link === 'https://docs.google.com/script-final') {
             $this->info("   ✅ Submitted with Links.");
        } else {
             $this->error("   ❌ Submission failed. Message: " . ($resComplete->getData(true)['message'] ?? 'Unknown Error'));
             return;
        }

        // 3. Producer Approves Normal Budget
        $this->info("\n[3] Producer Approves Normal Creative Work...");
        $this->actingAs($producer);
        $producerController = app(ProducerController::class);

        $producerController->approve(new Request([
            'type' => 'creative_work',
            'notes' => 'Looks good'
        ]), $workId);

        $work->refresh();
        if ($work->status === 'approved') {
             $this->info("   ✅ Normal Approval Success.");
        } else {
             $this->error("   ❌ Normal Approval Failed. Status: {$work->status}");
        }

        // 4. Test Special Budget Logic
        $this->info("\n[4] Testing Special Budget Flow...");
        // Reset work
        $work->update(['status' => 'submitted']);
        
        // Update budget to include Special
        $work->update([
            'budget_data' => [
                 ['category' => 'Talent', 'amount' => 5000000],
                 ['category' => 'Special Budget', 'amount' => 15000000, 'description' => 'Guest Star A-List']
            ]
        ]);

        // Producer tries to approve
        $resApprove = $producerController->approve(new Request([
            'type' => 'creative_work',
            'notes' => 'Need this expensive guest'
        ]), $workId);
        
        $work->refresh();
        if ($work->status === 'waiting_manager_approval') {
             $this->info("   ✅ Correctly flagged for Manager Approval (Status: waiting_manager_approval).");
             $approval = ProgramApproval::where('approvable_type', CreativeWork::class)
                ->where('approvable_id', $work->id)
                ->where('status', 'pending')
                ->latest()
                ->first();
             
             if ($approval && $approval->approval_type === 'special_budget') {
                 $this->info("   ✅ ProgramApproval record created.");
             } else {
                 $this->error("   ❌ ProgramApproval record missing or incorrect type.");
             }
        } else {
             $respData = $resApprove->getData(true);
             $this->error("   ❌ Failed to flag Special Budget. Status: {$work->status}");
             $this->error("      Response: " . json_encode($respData));
        }

        // 5. Test Producer Direct Edit (Bypass)
        $this->info("\n[5] Testing Producer Direct Edit (Bypass)...");
        // Reset again
        $work->update(['status' => 'submitted']); 

        $resEdit = $producerController->editCreativeWork(new Request([
            'script_content' => 'Producer Edited Script',
            'notes' => 'Directly fixed it',
        ]), $workId);

        $work->refresh();
        if ($work->status === 'approved' && $work->script_content === 'Producer Edited Script') {
             $this->info("   ✅ Direct Edit Success. Work Approved.");
        } else {
             $respData = $resEdit->getData(true);
             $this->error("   ❌ Direct Edit Failed. Status: {$work->status}");
             $this->error("      Response: " . json_encode($respData));
        }

        // 6. Test Ad-Hoc Team Assignment
        $this->info("\n[6] Testing Ad-Hoc Team Assignment...");
        
        $resAssign = $producerController->assignCreativeTeams(new Request([
            'episode_id' => $episode->id,
            'assignments' => [
                [
                    'type' => 'shooting',
                    'user_ids' => [$cameraman->id] // User OUTSIDE main team
                ],
                [
                    'type' => 'setting',
                    'user_ids' => [$artcrew->id]
                ]
            ]
        ]));

        $assignData = $resAssign->getData(true);
        if ($assignData['success']) {
             $this->info("   ✅ Assignment API Success.");
             
             // Verify DB
             $shootingAssign = ProductionTeamAssignment::where('episode_id', $episode->id)->where('team_type', 'shooting')->first();
             if ($shootingAssign) {
                 $member = ProductionTeamMember::where('assignment_id', $shootingAssign->id)->where('user_id', $cameraman->id)->first();
                 if ($member) {
                     $this->info("   ✅ Shooting Team Member assigned correctly.");
                 } else {
                     $this->error("   ❌ Cameraman not found in assignment members.");
                 }
             } else {
                 $this->error("   ❌ Shooting Assignment header not found.");
             }

        } else {
             $this->error("   ❌ Assignment API Failed: " . $assignData['message']);
        }

        $this->info("\n✅ PHASE 4 VERIFICATION FINISHED");
    }

    private function actingAs($user)
    {
        Auth::login($user);
    }
}
