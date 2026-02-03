<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\ProductionTeam;
use App\Models\ProductionTeamMember;
use App\Models\Episode;
use App\Models\MusicArrangement;
use Carbon\Carbon;

class VerifyPhase2 extends Command
{
    protected $signature = 'verify:phase2';
    protected $description = 'Verify Phase 2: Music Arrangement Workflow';

    public function handle()
    {
        $this->info("🚀 STARTING PHASE 2 VERIFICATION: MUSIC ARRANGEMENT");
        $this->info("==================================================");

        // 1. Setup (Reuse or Create) - minimal setup for Episode
        $this->info("\n[1] Setting up Environment...");
        
        $manager = User::firstOrCreate(
            ['email' => 'manager_prog_ver@test.com'],
            ['name' => 'Manager Program Ver', 'role' => 'Program Manager', 'phone' => '08123456789', 'password' => bcrypt('password')]
        );
        $producer = User::firstOrCreate(
            ['email' => 'producer_ver@test.com'],
            ['name' => 'Producer Ver', 'role' => 'Producer', 'phone' => '08123456790', 'password' => bcrypt('password')]
        );
        $arranger = User::firstOrCreate(
            ['email' => 'musik_arr_ver@test.com'],
            ['name' => 'Music Arranger Ver', 'role' => 'Music Arranger', 'phone' => '08123456791', 'password' => bcrypt('password')]
        );
        $se = User::firstOrCreate(
            ['email' => 'sound_eng_ver@test.com'],
            ['name' => 'Sound Engineer Ver', 'role' => 'Sound Engineer', 'phone' => '08123456792', 'password' => bcrypt('password')]
        );

        $program = Program::firstOrCreate(
            ['name' => 'Live Concert Verification'],
            [
                'manager_program_id' => $manager->id,
                'status' => 'active',
                'category' => 'musik',
                'start_date' => Carbon::parse('2026-01-01'),
                'air_time' => '20:00:00',
                'duration_minutes' => 60
            ]
        );

        $episode = Episode::firstOrCreate(
            ['program_id' => $program->id, 'episode_number' => 2], // Use Ep 2 to avoid conflict with Phase 1
            [
                'title' => 'Phase 2 Test Episode',
                'air_date' => Carbon::parse('2026-01-10'),
                'status' => 'planning'
            ]
        );
        $this->info("   ✅ Working with Episode: {$episode->episode_number} (ID: {$episode->id})");


        // 2. Lifecycle Verification
        $this->info("\n[2] Verifying Arrangement Lifecycle...");

        // A. Song Proposal
        $arragement = MusicArrangement::create([
            'episode_id' => $episode->id,
            'song_title' => 'Proposed Song A',
            'singer_name' => 'Singer A',
            'status' => 'song_proposal', // Initial status
            'created_by' => $arranger->id
        ]);
        $this->info("   ✅ [A] Song Proposal Submitted: {$arragement->song_title}");

        // B. Rejection (Song Proposal)
        $arragement->update([
            'status' => 'rejected',
            'notes' => 'Song choice not suitable'
        ]);
        $this->info("   ✅ [B] Producer Rejected Proposal.");

        // C. Revision & Approval (Song Proposal)
        $arragement->update([
            'song_title' => 'Approved Song B',
            'status' => 'song_proposal' // Resubmit
        ]);
        $arragement->update([
             'status' => 'arrangement_process' // Approved Proposal -> Move to Arrangement Process
        ]);
        $this->info("   ✅ [C] Proposal Revised & Approved. Status: arrangement_process");


        // D. Arrangement Submission
        $arragement->update([
            'file_path' => 'https://external-storage.com/arr_v1.mp3',
            'status' => 'submitted'
        ]);
        $this->info("   ✅ [D] Arrangement Submitted (Link V1).");

        // E. Rejection & SE Help Request
        $arragement->update([
            'status' => 'revision',
            'notes' => 'Needs better mixing, ask SE for help',
            'sound_engineer_help_notes' => 'Please fix EQ'
        ]);
        $this->info("   ✅ [E] Producer Rejected. Requested SE Help.");

        // F. SE Provides Help
        // SE updates the SAME record (or separate logic? Model has se_help_link)
        $arragement->update([
            'sound_engineer_help_file_link' => 'https://external-storage.com/se_help_v1.mp3'
        ]);
        $this->info("   ✅ [F] Sound Engineer Provided Help Link.");

        // G. Arranger Resubmits Final
        $arragement->update([
            'file_path' => 'https://external-storage.com/arr_final.mp3',
            'status' => 'submitted'
        ]);
        $this->info("   ✅ [G] Arranger Resubmitted Final.");

        // H. Final Approval
        $arragement->update([
            'status' => 'approved' // or 'completed'? check ENUM
        ]);
        
        $finalStatus = $arragement->fresh()->status;
        $this->info("   ✅ [H] Final Approval. Status: {$finalStatus}");

        if ($finalStatus === 'approved') {
             $this->info("\n✅ PHASE 2 VERIFICATION COMPLETE");
        } else {
             $this->error("\n❌ Phase 2 Failed: Final status is {$finalStatus}");
        }
    }
}
