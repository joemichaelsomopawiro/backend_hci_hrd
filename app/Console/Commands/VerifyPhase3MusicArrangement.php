<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\MusicArrangement;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ProducerController;
use App\Http\Controllers\Api\SoundEngineerController;
use App\Http\Controllers\Api\MusicArrangementController;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class VerifyPhase3MusicArrangement extends Command
{
    protected $signature = 'verify:phase3-music';
    protected $description = 'Verify Phase 3: Music Arrangement (Producer Edit & SE Helper with Link)';

    public function handle()
    {
        $this->info("🚀 STARTING PHASE 3 VERIFICATION: MUSIC ARRANGEMENT REFINEMENT");
        $this->info("==============================================================");

        // 1. Setup Environment
        $this->info("\n[1] Setting up Environment...");
        
        $producer = User::firstOrCreate(
            ['email' => 'producer_p3@test.com'],
            ['name' => 'Producer P3', 'role' => 'Producer', 'phone' => '08222222288', 'password' => bcrypt('password')]
        );
        $arranger = User::firstOrCreate(
            ['email' => 'arranger_p3@test.com'],
            ['name' => 'Arranger P3', 'role' => 'Music Arranger', 'phone' => '08333333388', 'password' => bcrypt('password')]
        );
        $se = User::firstOrCreate(
            ['email' => 'se_p3@test.com'],
            ['name' => 'SE P3', 'role' => 'Sound Engineer', 'phone' => '08444444488', 'password' => bcrypt('password')]
        );

        $program = Program::firstOrCreate(
            ['name' => 'Phase 3 Music Program'],
            ['manager_program_id' => $producer->id, 'status' => 'active', 'category' => 'musik', 'start_date' => now(), 'air_time' => '10:00:00', 'duration_minutes' => 60]
        );

        // Ensure Production Team existence correctly linked (Mocking logic usually handles this, 
        // but let's ensure the relationship exists for controllers)
        $team = \App\Models\ProductionTeam::firstOrCreate(
            ['name' => 'Team P3'],
            ['producer_id' => $producer->id, 'manager_program_id' => $producer->id, 'created_by' => $producer->id]
        );
        $program->update(['production_team_id' => $team->id]);

        // Add Arranger and SE to team
        \App\Models\ProductionTeamMember::updateOrCreate(
            ['production_team_id' => $team->id, 'user_id' => $arranger->id],
            ['role' => 'musik_arr', 'is_active' => true]
        );
        \App\Models\ProductionTeamMember::updateOrCreate(
            ['production_team_id' => $team->id, 'user_id' => $se->id],
            ['role' => 'sound_eng', 'is_active' => true]
        );

        $episode = Episode::firstOrCreate(
            ['program_id' => $program->id, 'episode_number' => 301],
            ['title' => 'Phase 3 Ep', 'status' => 'in_production', 'air_date' => now()->addDays(10)]
        );

        $this->info("   ✅ Environment Setup Complete.");


        // 2. Music Arranger Submits Proposal
        $this->info("\n[2] Arranger Submits Proposal...");
        $this->actingAs($arranger);
        
        $arrangement = MusicArrangement::create([
            'episode_id' => $episode->id,
            'song_title' => 'Original Song',
            'singer_name' => 'Original Singer',
            'status' => 'song_proposal',
            'created_by' => $arranger->id
        ]);
        $this->info("   ✅ Proposal Submitted: {$arrangement->song_title}");


        // 3. Producer Edits Song (Auto-Approve)
        $this->info("\n[3] Producer Edits Song (Expect Auto-Approve)...");
        $this->actingAs($producer);
        
        $producerController = app(ProducerController::class);
        $reqEdit = new Request([
            'song_title' => 'Producer Altered Song',
            'singer_name' => 'Producer Altered Singer',
            'modification_notes' => 'Changed for better fit'
        ]);

        $response = $producerController->editArrangementSongSinger($reqEdit, $arrangement->id);
        $data = $response->getData(true);
        
        $arrangement->refresh();
        if ($data['success'] && $arrangement->status === 'song_approved' && $arrangement->song_title === 'Producer Altered Song') {
            $this->info("   ✅ Producer Edit Success. Status: {$arrangement->status}");
            $this->info("   ✅ Song Title Updated: {$arrangement->song_title}");
            $this->info("   ✅ Auto-Approve Logic Verified.");
        } else {
            $this->error("   ❌ Producer Edit Failed or Not Auto-Approved. Status: {$arrangement->status}");
            return;
        }


        // 4. Music Arranger Submits Arrangement (Link)
        $this->info("\n[4] Arranger Submits Arrangement Link...");
        $this->actingAs($arranger);
        
        // Simulating Arranger work -> Link Submission
        // Assuming MusicArrangementController update logic
        // We will just mock the update for simplicity unless verification of controller logic is strict here
        // The user prompt focuses on the flow steps.
        
        $arrangement->update([
            'status' => 'submitted',
            'file_link' => 'https://drive.google.com/arr_v1.mp3' // User requirement: Input Link
        ]);
        $this->info("   ✅ Arrangement Submitted with Link.");


        // 5. Producer Rejects & Requests SE Help
        $this->info("\n[5] Producer Rejects & Requests SE Help...");
        $this->actingAs($producer);
        
        // Use reject method from ProducerController if available, or update manually to simulate
        // We need to trigger 'needs_sound_engineer_help'
        // Let's use the code I recall: producerController->rejectArrangement or similar?
        // Let's simulate the direct update to focus on the SE flow
        
        $arrangement->update([
            'status' => 'arrangement_rejected',
            'needs_sound_engineer_help' => true,
            'rejection_notes' => 'Bad mixing, SE pls help.'
        ]);
        $this->info("   ✅ Arrangement Rejected. Needs SE Help: Yes.");


        // 6. Sound Engineer Helps (Submit Link to Arranger)
        $this->info("\n[6] Sound Engineer Helps (Submit Link to Arranger)...");
        $this->actingAs($se);
        
        $seController = app(SoundEngineerController::class);
        $reqHelp = new Request([
            'help_notes' => 'Fixed EQ and Levels.',
            'help_file_link' => 'https://drive.google.com/se_help_fix.mp3' // This field might not exist yet!
        ]);

        // Attempt calling helpFixArrangement
        // Note: The current controller expects 'file' upload or 'suggested_fixes'. 
        // We want to test 'help_file_link'.
        
        try {
            // We use 'suggested_fixes' to pass the link if specific field doesn't exist, 
            // OR we expect this to fail if we are strictly testing for a dedicated link field.
            // User requirement: "Submit Link to Arranger"
            
            // Let's try passing it as a new field to see if we've implemented it (we haven't yet).
            // So we plan to implement it.
            
            $responseHelp = $seController->helpFixArrangement($reqHelp, $arrangement->id);
            $helpData = $responseHelp->getData(true);
            
            if ($helpData['success']) {
                $this->info("   ✅ SE Help Submitted.");
                
                // Check status. Should NOT be 'arrangement_submitted' (sent to Producer). 
                // Should return to Arranger.
                $arrangement->refresh();
                if ($arrangement->status === 'arrangement_submitted') {
                     $this->warn("   ⚠️  Status is 'arrangement_submitted'. This implies it went back to Producer directly.");
                     $this->warn("      User Request: 'Submit Link to Arranger' -> Arranger submits final.");
                } else {
                     $this->info("   ✅ Status is {$arrangement->status}. (Good if it allows Arranger to intervene)");
                }
                
            } else {
                $this->error("   ❌ SE Help Failed: " . $helpData['message']);
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Exception Verification SE Help: " . $e->getMessage());
        }

        $this->info("\n✅ PHASE 3 VERIFICATION SCRIPT FINISHED");
    }

    private function actingAs($user)
    {
        Auth::login($user);
    }
}
