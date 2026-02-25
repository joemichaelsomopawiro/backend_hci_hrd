<?php

use Illuminate\Support\Facades\Route;
use App\Models\PrEpisode;
use App\Models\PrProgram;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;

Route::get('/verify-step-6-v5', function () {
    // 1. Setup Test Data
    $program = PrProgram::firstOrCreate(
        ['name' => 'Test Program V5'],
        ['description' => 'Test Program', 'pr_dept_id' => 1, 'created_by' => 1]
    );

    $episode = PrEpisode::firstOrCreate(
        ['episode_number' => 9992, 'program_id' => $program->id],
        [
            'title' => 'Test Episode V5',
            'created_by' => 1,
            'air_date' => now()->addDays(7),
            'air_time' => '10:00:00',
            'status' => 'scheduled'
        ]
    );

    // Ensure workflow progress exists
    PrEpisodeWorkflowProgress::updateOrCreate(
        [
            'episode_id' => $episode->id,
            'workflow_step' => 6
        ],
        [
            'step_name' => 'Edit Konten',
            'responsible_role' => 'Editor, Editor Promosi, & Design Grafis',
            'status' => 'in_progress',
            'responsible_roles' => json_encode(['Editor', 'Editor Promosi', 'Design Grafis'])
        ]
    );

    $debugErrors = [];

    // 2. Create Works (Meeting criteria EXCEPT Design Grafis)

    // Editor: pending_qc
    try {
        PrEditorWork::updateOrCreate(
            ['pr_episode_id' => $episode->id],
            ['status' => 'pending_qc']
        );
    } catch (\Exception $e) {
        $debugErrors['editor'] = $e->getMessage();
    }

    // Promotion: completed
    try {
        $promo = PrPromotionWork::updateOrCreate(
            ['pr_episode_id' => $episode->id],
            ['status' => 'completed', 'work_type' => 'bts_video']
        );
    } catch (\Exception $e) {
        $debugErrors['promo'] = $e->getMessage();
    }

    // Editor Promosi: pending_qc
    try {
        if (isset($promo)) {
            PrEditorPromosiWork::updateOrCreate(
                ['pr_episode_id' => $episode->id],
                ['pr_promotion_work_id' => $promo->id, 'status' => 'pending_qc']
            );
        }
    } catch (\Exception $e) {
        $debugErrors['editor_promo'] = $e->getMessage();
    }

    // Design Grafis: PENDING (Should NOT trigger completion)
    try {
        PrDesignGrafisWork::updateOrCreate(
            ['pr_episode_id' => $episode->id],
            ['status' => 'pending', 'youtube_thumbnail_link' => 'http://test', 'bts_thumbnail_link' => 'http://test']
        );
    } catch (\Exception $e) {
        $debugErrors['design_grafis_draft'] = $e->getMessage();
    }

    // 3. Check Logic - Should be INCOMPLETE
    $service = app(\App\Services\PrWorkflowService::class);
    $dataDraft = $service->getWorkflowVisualization($episode->id);
    $step6Draft = collect($dataDraft['workflow']['steps'])->firstWhere('step_number', 6);
    $statusDraft = $step6Draft['status']; // Expected: in_progress

    // 4. Update Design Grafis to PENDING_QC (Should trigger completion)
    try {
        PrDesignGrafisWork::updateOrCreate(
            ['pr_episode_id' => $episode->id],
            ['status' => 'pending_qc']
        );
    } catch (\Exception $e) {
        $debugErrors['design_grafis_pending'] = $e->getMessage();
    }

    // 5. Check Logic - Should be COMPLETED
    $dataReady = $service->getWorkflowVisualization($episode->id);
    $step6Ready = collect($dataReady['workflow']['steps'])->firstWhere('step_number', 6);
    $statusReady = $step6Ready['status']; // Expected: completed

    return response()->json([
        'draft_check' => [
            'status' => $statusDraft,
            'passed' => $statusDraft !== 'completed'
        ],
        'ready_check' => [
            'status' => $statusReady,
            'passed' => $statusReady === 'completed'
        ],
        'debug_errors' => $debugErrors
    ]);
});

Route::get('/cleanup-step-6-v5', function () {
    $episode = PrEpisode::where('episode_number', 9992)->first();
    if ($episode) {
        PrEditorWork::where('pr_episode_id', $episode->id)->delete();
        PrEditorPromosiWork::where('pr_episode_id', $episode->id)->delete();
        PrPromotionWork::where('pr_episode_id', $episode->id)->delete();
        PrDesignGrafisWork::where('pr_episode_id', $episode->id)->delete();
        PrEpisodeWorkflowProgress::where('episode_id', $episode->id)->delete();
        $episode->delete();
    }
    PrProgram::where('name', 'Test Program V5')->delete();

    return response()->json(['cleaned' => true]);
});
