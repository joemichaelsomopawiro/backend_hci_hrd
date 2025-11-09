<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\MusicSubmission;
use App\Models\Song;
use App\Models\Singer;
use App\Models\User;
use App\Services\MusicWorkflowService;

class TestMusicSystem extends Command
{
    protected $signature = 'music:test-system';
    protected $description = 'Test Music Program System';

    public function handle()
    {
        $this->info('🎵 MUSIC PROGRAM SYSTEM TESTING');
        $this->info('================================');
        $this->newLine();

        // Test 1: Database Connection
        $this->info('1. Testing Database Connection...');
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection: OK');
        } catch (\Exception $e) {
            $this->error('❌ Database connection: FAILED - ' . $e->getMessage());
            return 1;
        }

        // Test 2: Check Required Tables
        $this->newLine();
        $this->info('2. Testing Required Tables...');
        $requiredTables = [
            'music_submissions',
            'songs', 
            'singers',
            'users',
            'music_workflow_states',
            'music_workflow_history',
            'music_workflow_notifications'
        ];

        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $this->info("✅ Table '$table': EXISTS");
            } else {
                $this->error("❌ Table '$table': MISSING");
            }
        }

        // Test 3: Check Models
        $this->newLine();
        $this->info('3. Testing Models...');
        try {
            $song = new Song();
            $this->info('✅ Song Model: OK');
        } catch (\Exception $e) {
            $this->error('❌ Song Model: FAILED - ' . $e->getMessage());
        }

        try {
            $singer = new Singer();
            $this->info('✅ Singer Model: OK');
        } catch (\Exception $e) {
            $this->error('❌ Singer Model: FAILED - ' . $e->getMessage());
        }

        try {
            $submission = new MusicSubmission();
            $this->info('✅ MusicSubmission Model: OK');
        } catch (\Exception $e) {
            $this->error('❌ MusicSubmission Model: FAILED - ' . $e->getMessage());
        }

        // Test 4: Check Service
        $this->newLine();
        $this->info('4. Testing MusicWorkflowService...');
        try {
            $service = new MusicWorkflowService();
            $this->info('✅ MusicWorkflowService: OK');
        } catch (\Exception $e) {
            $this->error('❌ MusicWorkflowService: FAILED - ' . $e->getMessage());
        }

        // Test 5: Check Workflow States
        $this->newLine();
        $this->info('5. Testing Workflow States...');
        $validStates = [
            'submitted', 'producer_review', 'arranging', 'arrangement_review',
            'sound_engineering', 'quality_control', 'creative_work', 
            'creative_review', 'producer_final_review', 'manager_approval',
            'general_affairs', 'promotion', 'production', 'sound_engineering_final',
            'final_approval', 'completed', 'rejected'
        ];

        $this->info('Valid workflow states:');
        foreach ($validStates as $state) {
            $this->line("  - $state");
        }

        // Test 6: Check Controllers
        $this->newLine();
        $this->info('6. Testing Controllers...');
        $controllers = [
            'MusicArrangerController',
            'ProducerMusicController', 
            'MusicWorkflowController',
            'CreativeController',
            'ManagerProgramController',
            'AudioController',
            'MusicNotificationController'
        ];

        foreach ($controllers as $controller) {
            $controllerClass = "App\\Http\\Controllers\\$controller";
            if (class_exists($controllerClass)) {
                $this->info("✅ $controller: EXISTS");
            } else {
                $this->error("❌ $controller: MISSING");
            }
        }

        // Test 7: Check Database Data
        $this->newLine();
        $this->info('7. Testing Database Data...');
        try {
            $songCount = Song::count();
            $this->info("✅ Songs in database: $songCount");
            
            $singerCount = Singer::count();
            $this->info("✅ Singers in database: $singerCount");
            
            $submissionCount = MusicSubmission::count();
            $this->info("✅ Music Submissions in database: $submissionCount");
            
            $userCount = User::count();
            $this->info("✅ Users in database: $userCount");
        } catch (\Exception $e) {
            $this->error('❌ Database data check: FAILED - ' . $e->getMessage());
        }

        // Test 8: Check API Routes
        $this->newLine();
        $this->info('8. Testing API Routes...');
        $routes = [
            'GET /api/music/health',
            'GET /api/music/status', 
            'GET /api/music/music-arranger/dashboard',
            'GET /api/music/music-arranger/songs',
            'POST /api/music/music-arranger/songs',
            'GET /api/music/music-arranger/singers',
            'POST /api/music/music-arranger/singers',
            'GET /api/music/producer/music/dashboard',
            'GET /api/music/producer/music/requests',
            'GET /api/music/music-workflow/list',
            'POST /api/music/music-workflow/submissions'
        ];

        $this->info('Expected API routes:');
        foreach ($routes as $route) {
            $this->line("  - $route");
        }

        $this->newLine();
        $this->info('🎵 MUSIC PROGRAM SYSTEM TESTING COMPLETED');
        $this->info('==========================================');
        
        $status = ($songCount > 0 || $singerCount > 0) ? "✅ READY" : "⚠️ NEEDS DATA";
        $this->info("Status: $status");

        return 0;
    }
}
