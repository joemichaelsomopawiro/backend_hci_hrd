<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrPromotionWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrPromosiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // AUTO-SYNC: Ensure all episodes with completed Step 4 have a Promotion Work
            // detailed logic to handle "lazy creation"
            $eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
                ->where('status', 'completed')
                ->pluck('episode_id');

            foreach ($eligibleEpisodes as $episodeId) {
                try {
                    // Check if promotion work exists
                    $exists = PrPromotionWork::where('pr_episode_id', $episodeId)->exists();

                    if (!$exists) {
                        // Get creative work to copy shooting date if possible, but for sync we keep it simple
                        // Best effort: try to find creative work associated
                        $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)->orderBy('created_at', 'desc')->first();

                        PrPromotionWork::create([
                            'pr_episode_id' => $episodeId,
                            'work_type' => 'bts_video', // Reverted to bts_video
                            'status' => 'planning',
                            'created_by' => $user->id,
                            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
                            'shooting_notes' => 'Auto-created from dashboard sync'
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Auto-sync failed for episode $episodeId: " . $e->getMessage());
                }
            }

            // SELF-HEALING: Fix inconsistencies where Production Work exists (implying approval)
            // but Promotion Work is missing OR Step 4 is not marked completed.
            try {
                // Get all episodes IDs that have Production Work
                $prodWorkEpisodeIds = \App\Models\PrProduksiWork::pluck('pr_episode_id')->unique();

                // Get all episodes IDs that have Promotion Work
                $promoWorkEpisodeIds = PrPromotionWork::whereIn('pr_episode_id', $prodWorkEpisodeIds)
                    ->pluck('pr_episode_id')
                    ->toArray();

                // Find missing ones
                $missingPromoEpisodeIds = $prodWorkEpisodeIds->diff($promoWorkEpisodeIds);

                foreach ($missingPromoEpisodeIds as $episodeId) {

                    // Create Missing Promotion Work
                    $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)->orderBy('created_at', 'desc')->first();

                    PrPromotionWork::create([
                        'pr_episode_id' => $episodeId,
                        'work_type' => 'bts_video',
                        'status' => 'planning',
                        'created_by' => $user->id,
                        'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
                        'shooting_notes' => 'Auto-created from self-healing sync'
                    ]);

                    // Verify and Fix Step 4 Status
                    $step4 = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                        ->where('workflow_step', 4)
                        ->first();

                    if ($step4 && $step4->status !== 'completed') {
                        $step4->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'notes' => 'Auto-completed via self-healing'
                        ]);
                    }
                }

                // ALSO: Check episodes with Production Work where Step 4 is NOT completed (even if Promo work exists)
                $incompleteStep4Episodes = \App\Models\PrEpisodeWorkflowProgress::whereIn('episode_id', $prodWorkEpisodeIds)
                    ->where('workflow_step', 4)
                    ->where('status', '!=', 'completed')
                    ->pluck('episode_id');

                foreach ($incompleteStep4Episodes as $episodeId) {
                    \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                        ->where('workflow_step', 4)
                        ->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'notes' => 'Auto-completed via self-healing (Production exists)'
                        ]);
                }

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Self-healing sync failed: " . $e->getMessage());
            }

            $query = PrPromotionWork::with(['episode.program', 'createdBy']);

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('work_type') && $request->work_type !== '') {
                $query->where('work_type', $request->work_type);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Promotion works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork($id)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            $work->update([
                'status' => 'shooting',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Work accepted successfully.',
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Find work with relationships
            $work = PrPromotionWork::with(['episode.program', 'episode.creativeWork', 'createdBy'])->find($id);

            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:planning,shooting,editing,sharing,completed',
                'work_type' => 'sometimes|string',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable',
                'location_data' => 'nullable',
                'shooting_notes' => 'nullable|string',
                'title' => 'nullable|string',
                'description' => 'nullable|string',
                'completion_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $request->only([
                'status',
                'work_type',
                'shooting_date',
                'shooting_time',
                'location_data',
                'shooting_notes',
                'title',
                'description',
                'completion_notes'
            ]);

            // Handle file_paths - model has array cast, so just pass the array
            if ($request->has('file_paths')) {
                $data['file_paths'] = $request->file_paths;
            }

            // Handle location_data - model has array cast
            if ($request->has('location_data')) {
                $data['location_data'] = $request->location_data;
            }

            $work->update($data);

            // Check workflow progress if completed
            if ($work->status === 'completed') {
                $this->checkAndUpdateWorkflowStep5($work->episode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Work updated successfully.',
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function complete($id)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            $work->update([
                'status' => 'completed',
                'completion_notes' => request('notes', $work->completion_notes)
            ]);

            $this->checkAndUpdateWorkflowStep5($work->episode);

            return response()->json([
                'success' => true,
                'message' => 'Work marked as completed.',
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Check if both promotion and production are complete for an episode,
     * and update workflow step 5 accordingly. Also create Step 6 work records.
     */
    private function checkAndUpdateWorkflowStep5($episode)
    {
        if (!$episode)
            return;

        // Check if promotion work is complete
        $promotionWork = PrPromotionWork::where('pr_episode_id', $episode->id)
            ->where('status', 'completed')
            ->first();

        // Check if production work is complete - Using PrProduksiWork model name corrected
        $productionWork = \App\Models\PrProduksiWork::where('pr_episode_id', $episode->id)
            ->where('status', 'completed')
            ->first();

        // If both are complete, update workflow step 5 and create Step 6 records
        if ($promotionWork && $productionWork) {
            // Fix: Use PrEpisodeWorkflowProgress instead of PrWorkflowStep
            $progress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
                ->where('workflow_step', 5)
                ->first();

            if ($progress) {
                $progress->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }

            // Auto-sync: Create Step 6 work records (Editor, Editor Promosi, Design Grafis)
            // Only create if they don't already exist

            // 1. Create Editor work
            \App\Models\PrEditorWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $productionWork->id,
                    'assigned_to' => null, // Can be assigned later
                    'status' => 'pending',
                    'files_complete' => false
                ]
            );

            // 2. Create Editor Promosi work
            \App\Models\PrEditorPromosiWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_editor_work_id' => null, // Will be linked when Editor work is created
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]
            );

            // 3. Create Design Grafis work
            \App\Models\PrDesignGrafisWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $productionWork->id,
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]
            );
        }
    }
}
