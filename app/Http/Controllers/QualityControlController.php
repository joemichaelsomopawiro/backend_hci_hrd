<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use App\Models\EpisodeQC;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Quality Control Controller
 * 
 * Workflow:
 * 1. Receive notification & file dari Editor
 * 2. Isi form catatan QC
 * 3. Approve atau Request Revision
 * 4. Notifikasi ke Producer untuk setiap hasil QC
 */
class QualityControlController extends Controller
{
    /**
     * Get episodes pending QC
     * GET /api/qc/episodes/pending
     */
    public function getPendingEpisodes(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular', 'editorWork'])
                ->where('status', 'post_production')
                ->whereHas('editorWork', function ($q) {
                    $q->where('status', 'completed')
                      ->whereNotNull('final_file_url');
                });

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            $episodes = $query->orderBy('air_date', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes pending QC retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific episode for QC
     * GET /api/qc/episodes/{id}
     */
    public function getEpisode(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::with([
                'programRegular',
                'editorWork',
                'qcHistory'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'editor_work' => $episode->editorWork,
                    'qc_history' => $episode->qcHistory
                ],
                'message' => 'Episode details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit QC review
     * POST /api/qc/episodes/{id}/review
     */
    public function submitReview(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'decision' => 'required|in:approved,revision_needed',
                'quality_score' => 'required|integer|min:1|max:10',
                'video_quality_score' => 'nullable|integer|min:1|max:10',
                'audio_quality_score' => 'nullable|integer|min:1|max:10',
                'content_quality_score' => 'nullable|integer|min:1|max:10',
                'notes' => 'required|string|max:2000',
                'revision_points' => 'nullable|array',
                'revision_points.*.category' => 'required_with:revision_points|in:video,audio,content,subtitle,transition,effect,other',
                'revision_points.*.description' => 'required_with:revision_points|string|max:500',
                'revision_points.*.priority' => 'required_with:revision_points|in:low,medium,high,critical'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create QC record
            $qc = EpisodeQC::create([
                'program_episode_id' => $episode->id,
                'qc_by' => auth()->id(),
                'decision' => $request->decision,
                'quality_score' => $request->quality_score,
                'video_quality_score' => $request->video_quality_score,
                'audio_quality_score' => $request->audio_quality_score,
                'content_quality_score' => $request->content_quality_score,
                'notes' => $request->notes,
                'revision_points' => $request->revision_points,
                'reviewed_at' => now(),
                'status' => $request->decision === 'approved' ? 'approved' : 'revision_needed'
            ]);

            // Update episode status based on QC decision
            if ($request->decision === 'approved') {
                $episode->update([
                    'status' => 'ready_to_air',
                    'qc_approved_at' => now(),
                    'qc_approved_by' => auth()->id()
                ]);

                // Notify Broadcasting team
                $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->orWhere('role', 'Distribution Manager')->get();
                foreach ($broadcastingUsers as $broadcastingUser) {
                    \App\Models\Notification::create([
                        'user_id' => $broadcastingUser->id,
                        'type' => 'qc_approved_broadcasting',
                        'title' => 'Episode Ready for Broadcasting',
                        'message' => "Episode {$episode->episode_number}: {$episode->title} has passed QC and is ready for broadcasting.",
                        'data' => [
                            'episode_id' => $episode->id,
                            'qc_id' => $qc->id,
                            'quality_score' => $request->quality_score
                        ]
                    ]);
                }
                
                // Update workflow state to broadcasting
                if ($episode->current_workflow_state === 'quality_control') {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'broadcasting',
                        'broadcasting',
                        null,
                        'QC approved, episode ready for broadcasting'
                    );
                }
            } else {
                // Send back to Editor
                $episode->update([
                    'status' => 'revision',
                    'qc_revision_requested_at' => now(),
                    'qc_revision_requested_by' => auth()->id(),
                    'qc_revision_count' => ($episode->qc_revision_count ?? 0) + 1
                ]);

                // Notify Editor
                $editors = \App\Models\User::where('role', 'Editor')->get();
                foreach ($editors as $editor) {
                    \App\Models\Notification::create([
                        'user_id' => $editor->id,
                        'type' => 'qc_revision_requested',
                        'title' => 'QC Revision Requested',
                        'message' => "Episode {$episode->episode_number}: {$episode->title} needs revision based on QC feedback.",
                        'data' => [
                            'episode_id' => $episode->id,
                            'qc_id' => $qc->id,
                            'revision_points' => $request->revision_points,
                            'revision_count' => ($episode->qc_revision_count ?? 0) + 1
                        ]
                    ]);
                }
                
                // Update workflow state back to editing
                if ($episode->current_workflow_state === 'quality_control') {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'editing',
                        'editor',
                        null,
                        'QC revision requested, sent back to editing'
                    );
                }
            }

            // Notify Producer in both cases
            $productionTeam = $episode->program->productionTeam;
            if ($productionTeam && $productionTeam->producer) {
                \App\Models\Notification::create([
                    'user_id' => $productionTeam->producer_id,
                    'type' => $request->decision === 'approved' ? 'qc_approved_producer' : 'qc_revision_producer',
                    'title' => $request->decision === 'approved' ? 'QC Approved' : 'QC Revision Requested',
                    'message' => "QC for Episode {$episode->episode_number}: {$episode->title} - " . ($request->decision === 'approved' ? 'Approved' : 'Revision Requested'),
                    'data' => [
                        'episode_id' => $episode->id,
                        'qc_id' => $qc->id,
                        'decision' => $request->decision,
                        'quality_score' => $request->quality_score
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'qc' => $qc,
                    'episode' => $episode->fresh()
                ],
                'message' => 'QC review submitted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error submitting QC review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get QC history for episode
     * GET /api/qc/episodes/{id}/history
     */
    public function getQCHistory(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);
            
            $history = EpisodeQC::where('program_episode_id', $episode->id)
                ->with('qcBy')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'QC history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving QC history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get QC statistics
     * GET /api/qc/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'pending_qc' => ProgramEpisode::where('status', 'post_production')->count(),
                'approved_today' => EpisodeQC::where('decision', 'approved')
                    ->whereDate('reviewed_at', today())
                    ->count(),
                'revision_requested_today' => EpisodeQC::where('decision', 'revision_needed')
                    ->whereDate('reviewed_at', today())
                    ->count(),
                'average_quality_score' => EpisodeQC::where('decision', 'approved')
                    ->avg('quality_score'),
                'total_revisions' => EpisodeQC::where('decision', 'revision_needed')->count(),
                'recent_reviews' => EpisodeQC::with(['episode', 'qcBy'])
                    ->orderBy('reviewed_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my QC tasks
     * GET /api/qc/my-tasks
     */
    public function getMyTasks(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $tasks = [
                'pending_review' => ProgramEpisode::where('status', 'post_production')
                    ->whereHas('editorWork', function ($q) {
                        $q->where('status', 'completed');
                    })
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'my_recent_reviews' => EpisodeQC::where('qc_by', $user->id)
                    ->with('episode')
                    ->orderBy('reviewed_at', 'desc')
                    ->limit(10)
                    ->get(),
                'urgent_episodes' => ProgramEpisode::where('status', 'post_production')
                    ->whereDate('air_date', '<=', now()->addDays(3))
                    ->orderBy('air_date', 'asc')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'Tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revision feedback for episode (untuk Editor)
     * GET /api/qc/episodes/{id}/revision-feedback
     */
    public function getRevisionFeedback(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);
            
            $latestQC = EpisodeQC::where('program_episode_id', $episode->id)
                ->where('decision', 'revision_needed')
                ->orderBy('reviewed_at', 'desc')
                ->first();

            if (!$latestQC) {
                return response()->json([
                    'success' => false,
                    'message' => 'No revision feedback found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qc' => $latestQC,
                    'revision_points' => $latestQC->revision_points,
                    'notes' => $latestQC->notes,
                    'revision_count' => $episode->qc_revision_count
                ],
                'message' => 'Revision feedback retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving revision feedback: ' . $e->getMessage()
            ], 500);
        }
    }
}

