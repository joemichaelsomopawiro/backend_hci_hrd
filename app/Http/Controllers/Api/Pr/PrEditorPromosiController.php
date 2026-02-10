<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrEditorPromosiController extends Controller
{
    /**
     * Get list of editor promosi works with filters
     */
    public function index(Request $request)
    {
        $query = PrEditorPromosiWork::with([
            'episode.program',
            'editorWork',
            'promotionWork',
            'assignedUser'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $works = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $works
        ]);
    }

    /**
     * Get detail of specific editor promosi work
     */
    public function show($id)
    {
        $work = PrEditorPromosiWork::with([
            'episode.program',
            'editorWork',
            'promotionWork',
            'assignedUser'
        ])->findOrFail($id);

        // Check if Editor has completed their work
        $editorReady = $work->editorWork && $work->editorWork->status === 'completed';

        return response()->json([
            'success' => true,
            'data' => $work,
            'editor_ready' => $editorReady
        ]);
    }

    /**
     * Check if Editor has completed their work for this episode
     */
    public function checkEditorStatus($episodeId)
    {
        $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)->first();

        if (!$editorWork) {
            return response()->json([
                'success' => false,
                'message' => 'Editor work not found',
                'editor_ready' => false
            ]);
        }

        $isReady = $editorWork->status === 'completed';

        return response()->json([
            'success' => true,
            'editor_ready' => $isReady,
            'editor_status' => $editorWork->status,
            'edited_video_link' => $isReady ? $editorWork->edited_video_link : null
        ]);
    }

    /**
     * Start working on an episode
     */
    public function start($episodeId)
    {
        try {
            $episode = PrEpisode::findOrFail($episodeId);

            $work = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();

            if (!$work) {
                return response()->json([
                    'success' => false,
                    'message' => 'Editor promosi work not found for this episode'
                ], 404);
            }

            // Check if Editor has completed
            $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)->first();
            $status = ($editorWork && $editorWork->status === 'completed') ? 'in_progress' : 'waiting_editor';

            $work->update([
                'status' => $status,
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Started working on episode',
                'data' => $work->load(['episode', 'editorWork', 'promotionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress - upload links
     */
    public function updateProgress(Request $request, $id)
    {
        $request->validate([
            'bts_video_link' => 'nullable|string',
            'tv_ad_link' => 'nullable|string',
            'ig_highlight_link' => 'nullable|string',
            'tv_highlight_link' => 'nullable|string',
            'fb_highlight_link' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $work = PrEditorPromosiWork::findOrFail($id);

            $updateData = $request->only([
                'bts_video_link',
                'tv_ad_link',
                'ig_highlight_link',
                'tv_highlight_link',
                'fb_highlight_link',
                'notes'
            ]);

            // If editor is not ready, keep status as waiting_editor
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($editorWork && $editorWork->status === 'completed' && $work->status === 'waiting_editor') {
                $updateData['status'] = 'in_progress';
            }

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Progress updated',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit completed work
     */
    public function submit($id)
    {
        try {
            DB::beginTransaction();

            $work = PrEditorPromosiWork::findOrFail($id);

            // Check if Editor has completed
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if (!$editorWork || $editorWork->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Editor belum menyelesaikan pekerjaannya. Harap tunggu atau hubungi Editor.'
                ], 400);
            }

            // Validate required fields
            $requiredFields = ['bts_video_link', 'tv_ad_link', 'ig_highlight_link', 'tv_highlight_link', 'fb_highlight_link'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($work->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (count($missingFields) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete all required links before submitting',
                    'missing_fields' => $missingFields
                ], 400);
            }

            $work->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Check if all Step 6 roles are completed
            $this->checkStep6Completion($work->pr_episode_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work submitted successfully',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if all Step 6 works are completed
     */
    private function checkStep6Completion($episodeId)
    {
        $episode = PrEpisode::findOrFail($episodeId);

        $editorCompleted = PrEditorWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $editorPromosiCompleted = PrEditorPromosiWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $designGrafisCompleted = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        // If all three are completed, mark Step 6 as completed
        if ($editorCompleted && $editorPromosiCompleted && $designGrafisCompleted) {
            $episode->update([
                'workflow_step' => 7, // Move to next step
                'status' => 'step_6_completed'
            ]);
        }
    }
}
