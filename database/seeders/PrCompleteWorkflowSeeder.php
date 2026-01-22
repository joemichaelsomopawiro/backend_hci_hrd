<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrConcept;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrQualityControlWork;
use App\Models\PrBroadcastingWork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PrCompleteWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates complete sample data for testing the entire PR 10-step workflow
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Get or create users by role
            $managerProgram = User::firstOrCreate(
                ['email' => 'manager_program@tvku.com'],
                [
                    'name' => 'Manager Program Test',
                    'role' => 'Manager Program',
                    'password' => Hash::make('password'),
                    'phone' => '081234567890'
                ]
            );

            $producer = User::firstOrCreate(
                ['email' => 'producer@tvku.com'],
                [
                    'name' => 'Producer Test',
                    'role' => 'Producer',
                    'password' => Hash::make('password'),
                    'phone' => '081234567891'
                ]
            );

            $creative = User::firstOrCreate(
                ['email' => 'creative@tvku.com'],
                [
                    'name' => 'Creative Test',
                    'role' => 'Creative',
                    'password' => Hash::make('password'),
                    'phone' => '081234567892'
                ]
            );

            $produksi = User::firstOrCreate(
                ['email' => 'produksi@tvku.com'],
                [
                    'name' => 'Produksi Test',
                    'role' => 'Produksi',
                    'password' => Hash::make('password'),
                    'phone' => '081234567893'
                ]
            );

            $editor = User::firstOrCreate(
                ['email' => 'editor@tvku.com'],
                [
                    'name' => 'Editor Test',
                    'role' => 'Editor',
                    'password' => Hash::make('password'),
                    'phone' => '081234567894'
                ]
            );

            $qc = User::firstOrCreate(
                ['email' => 'qc@tvku.com'],
                [
                    'name' => 'QC Test',
                    'role' => 'QC',
                    'password' => Hash::make('password'),
                    'phone' => '081234567895'
                ]
            );

            $broadcasting = User::firstOrCreate(
                ['email' => 'broadcasting@tvku.com'],
                [
                    'name' => 'Broadcasting Test',
                    'role' => 'Broadcasting',
                    'password' => Hash::make('password'),
                    'phone' => '081234567896'
                ]
            );

            $promosi = User::firstOrCreate(
                ['email' => 'promosi@tvku.com'],
                [
                    'name' => 'Promosi Test',
                    'role' => 'Promosi',
                    'password' => Hash::make('password'),
                    'phone' => '081234567897'
                ]
            );

            $this->command->info('Creating PR Program...');

            // 1. Create Program Regular
            $program = PrProgram::create([
                'name' => 'Inspirasi Pagi ' . rand(100, 999), // Unique name
                'description' => 'Program inspirasi untuk memulai hari dengan semangat',
                'manager_program_id' => $managerProgram->id,
                'producer_id' => $producer->id,
                'status' => 'concept_approved',
                'start_date' => now()->addDays(30),
                'air_time' => '06:00:00',
                'duration_minutes' => 30,
                'broadcast_channel' => 'TVKU Main',
                'program_year' => now()->year,
            ]);

            $this->command->info("✓ Program created: {$program->name}");

            // 2. Create Concept
            $conceptData = [
                'program_id' => $program->id,
                'concept' => 'Konsep Inspirasi Pagi - Season 1',
                'format_description' => 'Talk show dengan narasumber. Program motivasi pagi dengan narasumber inspiratif.',
                'target_audience' => 'Dewasa 25-45 tahun',
                'content_outline' => 'Episode 1-10: Self Improvement. Episode 11-20: Career & Business.',
                'objectives' => 'Memberikan inspirasi positif dan praktis bagi penonton.',
                'status' => 'approved',
                'approved_by' => $producer->id,
                'approved_at' => now()->subDays(6),
                'approval_notes' => 'Konsep bagus, sesuai target audience',
                'created_by' => $managerProgram->id,
            ];

            // Only add read_by if column exists
            if (Schema::hasColumn('pr_program_concepts', 'read_by')) {
                $conceptData['read_by'] = $producer->id;
                $conceptData['read_at'] = now()->subDays(5);
            }

            $concept = PrConcept::create($conceptData);

            $this->command->info("✓ Concept created and approved");

            // 3. Create Episodes
            $this->command->info('Creating episodes...');

            $episode1 = PrEpisode::create([
                'program_id' => $program->id,
                'episode_number' => 1,
                'title' => 'Menemukan Tujuan Hidup',
                'description' => 'Berdiskusi tentang bagaimana menemukan purpose dalam hidup',
                'air_date' => now()->addDays(30),
                'air_time' => '06:00:00',
                'status' => 'production',
            ]);

            $this->command->info("✓ Created episode 1");

            // 4. WORKFLOW STEP 4: Creative Work
            $this->command->info('Setting up Creative Work...');

            $creativeWork = PrCreativeWork::create([
                'pr_episode_id' => $episode1->id,
                'status' => 'approved',
                'script_content' => 'Opening: Selamat pagi pemirsa... [full script content here]',
                'storyboard_data' => [
                    'scenes' => ['Shot 1: Wide shot', 'Shot 2: Close up'],
                ],
                'budget_data' => [
                    'items' => [
                        ['item' => 'Talent', 'cost' => 3000000],
                        ['item' => 'Props', 'cost' => 1000000]
                    ],
                    'total' => 4000000
                ],
                'shooting_schedule' => now()->addDays(20),
                'script_approved' => true,
                'script_approved_by' => $producer->id,
                'script_approved_at' => now()->subDays(3),
                'storyboard_approved' => true,
                'storyboard_approved_by' => $producer->id,
                'storyboard_approved_at' => now()->subDays(3),
                'budget_approved' => true,
                'budget_approved_by' => $producer->id,
                'budget_approved_at' => now()->subDays(3),
                'created_by' => $creative->id,
                'reviewed_by' => $producer->id,
                'reviewed_at' => now()->subDays(3),
            ]);

            $this->command->info("✓ Creative work created and approved");

            // 5. WORKFLOW STEP 5: Produksi
            $this->command->info('Setting up Produksi Work...');

            $produksiWork = PrProduksiWork::create([
                'pr_episode_id' => $episode1->id,
                'pr_creative_work_id' => $creativeWork->id,
                'status' => 'completed',
                'equipment_requests' => [
                    ['item' => 'Camera Sony', 'qty' => 2],
                    ['item' => 'Lighting Kit', 'qty' => 1]
                ],
                'shooting_files' => [
                    ['filename' => 'CAM1.mp4', 'size' => '10GB'],
                    ['filename' => 'CAM2.mp4', 'size' => '10GB']
                ],
                'shooting_notes' => 'Shooting lancar.',
                'created_by' => $produksi->id,
                'completed_by' => $produksi->id,
                'completed_at' => now()->subHours(12),
            ]);

            $this->command->info("✓ Produksi work completed");

            // 6. WORKFLOW STEP 6: Editor
            $this->command->info('Setting up Editor Work...');

            $editorWork = PrEditorWork::create([
                'pr_episode_id' => $episode1->id,
                'status' => 'completed',
                'source_files' => [
                    'produksi_files' => ['CAM1.mp4', 'CAM2.mp4']
                ],
                'file_name' => 'EP01_Final.mp4',
                'file_path' => 'videos/EP01_Final.mp4',
                'file_size' => '2GB',
                'mime_type' => 'video/mp4',
                'editing_notes' => 'Color graded and mixed.',
                'file_complete' => true,
                'created_by' => $editor->id,
            ]);

            $this->command->info("✓ Editor work completed");

            // 7-8. WORKFLOW STEPS 7-8: Quality Control
            $this->command->info('Setting up QC Work...');

            $qcWork = PrQualityControlWork::create([
                'pr_episode_id' => $episode1->id,
                'status' => 'approved',
                'qc_checklist' => [
                    'video' => 'pass',
                    'audio' => 'pass',
                    'content' => 'pass'
                ],
                'qc_results' => [
                    'status' => 'pass',
                    'score' => 95
                ],
                'qc_notes' => 'Ready for broadcast.',
                'screenshots' => [
                    'screen1.jpg',
                    'screen2.jpg'
                ],
                'created_by' => $qc->id,
                'reviewed_by' => $qc->id,
                'reviewed_at' => now()->subHours(2),
            ]);

            $this->command->info("✓ QC work completed and approved");

            // 9. WORKFLOW STEP 9: Broadcasting
            $this->command->info('Setting up Broadcasting Work...');

            $broadcastingWork = PrBroadcastingWork::create([
                'pr_episode_id' => $episode1->id,
                'status' => 'published',
                'youtube_url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
                'youtube_video_id' => 'dQw4w9WgXcQ',
                'website_url' => 'https://tvku.id/program/inspirasi-pagi/1',
                'notes' => 'Published successfully',
                'scheduled_time' => now()->addDays(5),
                'published_at' => now()->subHours(1),
                'created_by' => $broadcasting->id,
            ]);

            $this->command->info("✓ Broadcasting work completed and published");

            // 10. WORKFLOW STEP 10: Promosi
            $this->command->info('Setting up Promosi Work...');

            $promosiWork = PrPromotionWork::create([
                'pr_episode_id' => $episode1->id,
                'status' => 'completed',
                'file_paths' => [
                    'bts' => ['bts1.jpg', 'bts2.jpg'],
                    'promo' => ['poster.jpg']
                ],
                'sharing_proof' => [
                    'facebook' => 'https://fb.com/post/1',
                    'instagram' => 'https://instagr.am/p/1'
                ],
                'created_by' => $promosi->id,
            ]);

            $this->command->info("✓ Promosi work completed");

            // Update episode status
            $episode1->update(['status' => 'ready_for_review']);

            DB::commit();

            $this->command->info("\n========================================");
            $this->command->info("✓ PR Complete Workflow Seeder Success!");
            $this->command->info("========================================");
            $this->command->info("Program: {$program->name}");
            $this->command->info("Concept: {$concept->concept} (Approved)");
            $this->command->info("Episodes: {$program->episodes()->count()}");
            $this->command->info("\nAll 10 workflow steps have sample data! 🎉");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("\nSEEDER FAILED!");
            $this->command->error("Message: " . $e->getMessage());
            $this->command->error("File: " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
