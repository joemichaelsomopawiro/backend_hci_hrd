<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\PromotionWork;
use App\Models\BroadcastingWork;
use App\Models\ProductionTeam;
use App\Http\Controllers\Api\BroadcastingController;
use App\Http\Controllers\Api\PromosiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VerifyPhase8Finalization extends Command
{
    protected $signature = 'verify:phase8-finalization {--cleanup : Delete the created data after run}';
    protected $description = 'Verify Phase 8: Broadcasting & Promotion Finalization Workflow';

    public function handle()
    {
        $this->info("🚀 Starting Phase 8 Verification: Broadcasting & Promotion...");
        $uniqueId = uniqid();
        Storage::fake('public');

        try {
            // 1. Setup Data
            $this->info("\n[1] Setting up Data...");
            
            $broadcastingUser = User::where('role', 'Broadcasting')->first() ?? User::create([
                'name' => 'Verify Broadcasting ' . $uniqueId,
                'email' => "broadcasting8_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Broadcasting'
            ]);
            
            $promosiUser = User::where('role', 'Promotion')->first() ?? User::create([
                'name' => 'Verify Promotion ' . $uniqueId,
                'email' => "promosi8_{$uniqueId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'Promotion'
            ]);

            $pm = User::where('role', 'Program Manager')->first() ?? User::create([
                'name' => 'Verify PM ' . $uniqueId,
                'email' => "pm8_{$uniqueId}@example.com",
                'role' => 'Program Manager',
                'password' => bcrypt('password')
            ]);

            $team = ProductionTeam::create([
                'name' => 'Team Phase 8 ' . $uniqueId,
                'producer_id' => $pm->id, // Use PM as producer for simplicity
                'created_by' => $pm->id,
                'is_active' => 1
            ]);
            
            $program = Program::create([
                'name' => 'Phase 8 Program ' . $uniqueId,
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
                'title' => 'Phase 8 Finalization Integration Test',
                'status' => 'in_production',
                'air_date' => now()->addDays(7),
                'production_date' => now()->addDays(2),
                'production_deadline' => now()->addDays(5),
                'current_workflow_state' => 'broadcasting',
                'production_team_id' => $team->id
            ]);

            $broadcastingWork = BroadcastingWork::create([
                'episode_id' => $episode->id,
                'work_type' => 'main_episode',
                'title' => 'Broadcasting for ' . $episode->title,
                'status' => 'preparing',
                'video_file_path' => 'final/video.mp4',
                'thumbnail_path' => 'final/thumb.jpg',
                'created_by' => $broadcastingUser->id
            ]);

            // Existing story_ig and reels_facebook for update check
            PromotionWork::create([
                'episode_id' => $episode->id,
                'work_type' => 'story_ig',
                'title' => 'Story IG for ' . $episode->title,
                'status' => 'editing',
                'created_by' => $promosiUser->id
            ]);

            PromotionWork::create([
                'episode_id' => $episode->id,
                'work_type' => 'reels_facebook',
                'title' => 'Reels FB for ' . $episode->title,
                'status' => 'editing',
                'created_by' => $promosiUser->id
            ]);

            $this->info("   ✅ Data created. Episode ID: {$episode->id}, Broadcasting Work ID: {$broadcastingWork->id}");

            // 2. Broadcasting Completes Work
            $this->info("\n[2] Broadcasting Completes Work (Upload)...");
            $this->actingAs($broadcastingUser);
            $broadcastingController = app(BroadcastingController::class);
            
            $resComplete = $broadcastingController->completeWork(new Request([
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'website_url' => 'https://example.com/episode/' . $episode->id,
                'completion_notes' => 'Published successfully.'
            ]), $broadcastingWork->id);

            if ($resComplete->getData()->success) {
                $this->info("   ✅ Broadcasting completed work.");
                $this->info("      YouTube URL: " . $broadcastingWork->fresh()->youtube_url);
            } else {
                $this->error("   ❌ Failed to complete broadcasting work: " . ($resComplete->getData()->message ?? 'Unknown error'));
                return 1;
            }

            // 3. Verify PromotionWork Tasks Created/Updated
            $this->info("\n[3] Verifying PromotionWork tasks...");
            $shareFB = PromotionWork::where('episode_id', $episode->id)->where('work_type', 'share_facebook')->first();
            $shareWA = PromotionWork::where('episode_id', $episode->id)->where('work_type', 'share_wa_group')->first();
            $storyIG = PromotionWork::where('episode_id', $episode->id)->where('work_type', 'story_ig')->first();
            
            if ($shareFB && $shareWA) {
                $this->info("   ✅ Sharing tasks (FB, WA) auto-created.");
                $this->info("      Source Link check: " . ($shareFB->social_media_links['youtube_url'] ?? 'Missing'));
            } else {
                $this->error("   ❌ Sharing tasks NOT auto-created.");
                return 1;
            }

            if ($storyIG->social_media_links && isset($storyIG->social_media_links['youtube_url'])) {
                $this->info("   ✅ Existing Story IG task updated with source links.");
            } else {
                $this->error("   ❌ Existing task NOT updated.");
                return 1;
            }

            // 4. Promotion Submits Proof
            $this->info("\n[4] Promotion Submits Proof...");
            $this->actingAs($promosiUser);
            $promosiController = app(PromosiController::class);

            // Test Facebook Share Proof
            $this->info("   - Testing shareFacebook...");
            $reqFB = new Request([
                'facebook_post_url' => 'https://facebook.com/posts/1',
                'notes' => 'Posted to FB'
            ]);
            $reqFB->files->set('proof_file', UploadedFile::fake()->image('fb_proof.png'));
            $resFB = $promosiController->shareFacebook($reqFB, $shareFB->id);

            if ($resFB->isSuccessful()) {
                $freshFB = $shareFB->fresh();
                if (isset($freshFB->social_media_proof['facebook_share'])) {
                    $this->info("   ✅ Facebook proof saved to 'social_media_proof'.");
                } else {
                    $this->error("   ❌ Facebook proof NOT found in 'social_media_proof'.");
                }
            } else {
                $this->error("   ❌ Failed shareFacebook: " . (json_decode($resFB->getContent())->message ?? 'Unknown error'));
            }

            // Test WA Group Proof
            $this->info("   - Testing shareWAGroup...");
            $reqWA = new Request([
                'group_name' => 'Promotion Group 1',
                'notes' => 'Shared to WA'
            ]);
            $reqWA->files->set('proof_file', UploadedFile::fake()->image('wa_proof.png'));
            $resWA = $promosiController->shareWAGroup($reqWA, $shareWA->id);

            if ($resWA->isSuccessful()) {
                $freshWA = $shareWA->fresh();
                if (isset($freshWA->social_media_proof['wa_group_share'])) {
                    $this->info("   ✅ WA proof saved to 'social_media_proof'.");
                } else {
                    $this->error("   ❌ WA proof NOT found in 'social_media_proof'.");
                }
            } else {
                $this->error("   ❌ Failed shareWAGroup: " . (json_decode($resWA->getContent())->message ?? 'Unknown error'));
            }

            if ($this->option('cleanup')) {
                $this->info("\n[Cleanup] Deleting created data...");
                $episode->delete();
                $program->delete();
                $team->delete();
                $this->info("   ✅ Cleanup done.");
            }

            $this->info("\n🎉 Phase 8 Verification Completed Successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Phase 8 Verification Failed!");
            $this->error("      Error: " . $e->getMessage());
            $this->error("      Line: " . $e->getLine());
            $this->error("      Trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    private function actingAs($user)
    {
        Auth::login($user);
    }
}
