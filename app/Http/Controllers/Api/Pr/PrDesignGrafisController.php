<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorWork;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrDesignGrafisController extends Controller
{
    /**
     * Get list of design grafis works with filters
     */
    public function index(Request $request)
    {
        $query = PrDesignGrafisWork::with([
            'episode.program',
            'productionWork',
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
     * Get detail of specific design grafis work
     */
    public function show($id)
    {
        $work = PrDesignGrafisWork::with([
            'episode.program',
            'productionWork.shootingSchedule',
            'productionWork.files',
            'promotionWork',
            'assignedUser'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $work
        ]);
    }

    /**
     * Start working on an episode
     */
    public function start($episodeId)
    {
        try {
            $episode = PrEpisode::findOrFail($episodeId);

            $work = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

            if (!$work) {
                return response()->json([
                    'success' => false,
                    'message' => 'Design grafis work not found for this episode'
                ], 404);
            }

            $work->update([
                'status' => 'in_progress',
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Started working on episode',
                'data' => $work->load(['episode', 'productionWork', 'promotionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress - upload thumbnail links
     */
    public function updateProgress(Request $request, $id)
    {
        $request->validate([
            'youtube_thumbnail_link' => 'nullable|string',
            'bts_thumbnail_link' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $work = PrDesignGrafisWork::findOrFail($id);

            $updateData = $request->only([
                'youtube_thumbnail_link',
                'bts_thumbnail_link',
                'notes'
            ]);

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

            $work = PrDesignGrafisWork::findOrFail($id);

            // Validate required fields
            if (empty($work->youtube_thumbnail_link) || empty($work->bts_thumbnail_link)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload both YouTube and BTS thumbnails before submitting'
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

        $editorPromosiCompleted = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $designGrafisCompleted = PrDesignGrafisWork::where('pr_episode_id', $episodeId)
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
