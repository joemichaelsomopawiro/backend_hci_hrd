<?php

namespace Tests\Feature;

use App\Models\BroadcastingWork;
use App\Models\DesignGrafisWork;
use App\Models\EditorWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\ProduksiWork;
use App\Models\Program;
use App\Models\PromotionWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected $producer;
    protected $designer;
    protected $editor;
    protected $editorPromosi;
    protected $broadcaster;
    protected $promosi;
    protected $program;
    protected $episode;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Users
        $this->producer = User::factory()->create(['role' => 'Production', 'name' => 'Producer User']);
        $this->designer = User::factory()->create(['role' => 'Graphic Design', 'name' => 'Designer User']);
        $this->editor = User::factory()->create(['role' => 'Editor', 'name' => 'Editor User']);
        $this->editorPromosi = User::factory()->create(['role' => 'Editor Promotion', 'name' => 'Editor Promosi User']);
        $this->broadcaster = User::factory()->create(['role' => 'Broadcasting', 'name' => 'Broadcaster User']);
        $this->promosi = User::factory()->create(['role' => 'Promotion', 'name' => 'Promosi User']);

        // Create Program & Production Team
        $this->program = Program::factory()->create();
        // production team setup if needed...

        // Create Episode
        $this->episode = Episode::factory()->create([
            'program_id' => $this->program->id,
            'episode_number' => 1
        ]);
    }

    /** @test */
    public function produksi_completion_triggers_design_grafis_work()
    {
        // 1. Create ProduksiWork
        $produksiWork = ProduksiWork::create([
            'episode_id' => $this->episode->id,
            'status' => 'in_progress',
            'created_by' => $this->producer->id,
            'shooting_files' => ['file1.mp4']
        ]);

        // 2. Complete Work (Call API or Controller method)
        // We simulate the API call
        $response = $this->actingAs($this->producer)
            ->postJson("/api/live-tv/roles/produksi/works/{$produksiWork->id}/complete-work", [
                'shooting_notes' => 'Done',
                'shooting_file_links' => [['url' => 'http://example.com', 'description' => 'Shooting Files']]
            ]);

        $response->assertStatus(200);

        // 3. Assert DesignGrafisWork (thumbnail_youtube) Created
        $this->assertDatabaseHas('design_grafis_works', [
            'episode_id' => $this->episode->id,
            'work_type' => 'thumbnail_youtube',
            'status' => 'draft'
        ]);

        // 4. Assert Notification Sent to Designer
        $this->assertDatabaseHas('notifications', [
            'type' => 'design_work_assigned',
            'user_id' => $this->designer->id
        ]);
    }

    /** @test */
    public function broadcasting_completion_triggers_promosi_sharing_works()
    {
        // 1. Create BroadcastingWork
        $broadcastingWork = BroadcastingWork::create([
            'episode_id' => $this->episode->id,
            'work_type' => 'main_episode',
            'title' => 'Main Episode Upload',
            'status' => 'uploading',
            'created_by' => $this->broadcaster->id
        ]);

        // 2. Complete Work with YouTube URL
        $youtubeUrl = 'https://youtube.com/watch?v=12345678901';
        $websiteUrl = 'https://mysite.com/episode/1';

        $response = $this->actingAs($this->broadcaster)
            ->postJson("/api/live-tv/broadcasting/works/{$broadcastingWork->id}/complete-work", [
                'youtube_url' => $youtubeUrl,
                'website_url' => $websiteUrl,
                'completion_notes' => 'Done uploading'
            ]);

        $response->assertStatus(200);

        // 3. Assert Promotion Works Created
        $types = ['share_facebook', 'story_ig', 'reels_facebook'];
        
        foreach ($types as $type) {
            $this->assertDatabaseHas('promotion_works', [
                'episode_id' => $this->episode->id,
                'work_type' => $type
            ]);
            
            // Verify links in social_media_links JSON
            $work = PromotionWork::where('episode_id', $this->episode->id)
                ->where('work_type', $type)
                ->first();
                
            $this->assertNotNull($work);
            $this->assertEquals($youtubeUrl, $work->social_media_links['youtube_url'] ?? null);
            $this->assertEquals($websiteUrl, $work->social_media_links['website_url'] ?? null);
        }
    }

    /** @test */
    public function editor_submission_triggers_editor_promosi_works()
    {
        // 1. Create EditorWork
        $editorWork = EditorWork::create([
            'episode_id' => $this->episode->id,
            'work_type' => 'main_episode',
            'status' => 'editing',
            'created_by' => $this->editor->id,
            'file_link' => 'http://drive.com/edited_file.mp4' // Mock file link
        ]);

        // 2. Submit Work
        $response = $this->actingAs($this->editor)
            ->postJson("/api/live-tv/editor/works/{$editorWork->id}/submit", [
                'submission_notes' => 'Final Edit Ready'
            ]);

        $response->assertStatus(200);

        // 3. Assert Promotion Works for Editor Promotion Created
        $types = ['bts_video', 'highlight_ig', 'highlight_tv', 'highlight_facebook'];

        foreach ($types as $type) {
            $this->assertDatabaseHas('promotion_works', [
                'episode_id' => $this->episode->id,
                'work_type' => $type
            ]);

            $work = PromotionWork::where('episode_id', $this->episode->id)
                ->where('work_type', $type)
                ->first();
            
            // Check if file_paths JSON contains the editor link
            $this->assertNotNull($work->file_paths['editor_file_link'] ?? null);
            $this->assertEquals('http://drive.com/edited_file.mp4', $work->file_paths['editor_file_link']);
        }
    }
}
