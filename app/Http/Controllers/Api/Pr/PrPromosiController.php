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

            $query = PrPromotionWork::with(['episode.program', 'episode.creativeWork', 'createdBy']);

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

    public function acceptWork(int $id)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::findOrFail($id);

            if ($work->status !== 'planning') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when planning'], 400);
            }

            $work->update([
                'status' => 'shooting',
                'created_by' => $user->id
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function uploadContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_paths' => 'required|array|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrPromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $work->update([
                'file_paths' => $request->file_paths,
                'status' => 'completed'
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Content uploaded successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function shareContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'platform' => 'required|in:facebook,whatsapp,instagram',
                'proof_url' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrPromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $sharingProof = $work->sharing_proof ?? [];
            $sharingProof[] = [
                'platform' => $request->platform,
                'proof_url' => $request->proof_url,
                'shared_at' => now()->toDateTimeString()
            ];

            $work->update([
                'sharing_proof' => $sharingProof,
                'status' => 'completed'
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Content shared successfully']);

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
    /**
     * Get all episodes that have a promotion work (for Share Konten dropdown)
     */
    public function getEpisodes(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $episodes = \App\Models\PrEpisode::with(['program', 'promotionWork', 'broadcastingWork', 'editorPromosiWork'])
                ->whereHas('promotionWork')
                ->whereHas('workflowProgress', function ($query) {
                    $query->where('workflow_step', 8)->where('status', 'completed');
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($episode) {
                    $isPromoted = $episode->status === 'promoted';
                    $hasSharingTasks = !empty($episode->promotionWork?->sharing_proof['share_konten_tasks']);
                    $workStatus = $isPromoted ? 'completed' : ($hasSharingTasks ? 'in_progress' : 'pending');

                    return [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title ?? ('Episode ' . $episode->episode_number),
                        'program_name' => $episode->program?->name ?? '',
                        'youtube_link' => $episode->broadcastingWork?->youtube_url ?? null,
                        'work_status' => $workStatus,
                        'last_edited' => $episode->promotionWork?->updated_at ? $episode->promotionWork->updated_at->format('Y-m-d H:i') : null,
                        'status' => $episode->status,
                    ];
                });

            return response()->json(['success' => true, 'data' => $episodes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get saved Share Konten task progress for an episode
     */
    public function getShareKonten(int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $promotionWork = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

            if (!$promotionWork) {
                return response()->json(['success' => false, 'message' => 'No promotion work found for this episode'], 404);
            }

            $sharingProof = $promotionWork->sharing_proof ?? [];
            $tasks = $sharingProof['share_konten_tasks'] ?? null;

            // Load highlight links from EditorPromosiWork
            $editorPromosi = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
            $igHighlightLink = $editorPromosi?->ig_highlight_link ?? null;
            $fbHighlightLink = $editorPromosi?->fb_highlight_link ?? null;

            // Inject highlight links into tasks so frontend always has fresh data from DB
            if ($tasks) {
                if (isset($tasks['story_ig'])) {
                    $tasks['story_ig']['video_link'] = $igHighlightLink;
                }
                if (isset($tasks['reels_fb'])) {
                    $tasks['reels_fb']['video_link'] = $fbHighlightLink;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'ig_highlight_link' => $igHighlightLink,
                'fb_highlight_link' => $fbHighlightLink,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save Share Konten task progress for an episode
     */
    public function saveShareKonten(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $promotionWork = PrPromotionWork::with(['episode.program'])->where('pr_episode_id', $episodeId)->first();

            if (!$promotionWork) {
                return response()->json(['success' => false, 'message' => 'No promotion work found for this episode'], 404);
            }

            $tasks = $request->input('tasks');

            // Merge into sharing_proof under dedicated key to avoid overwriting other data
            $sharingProof = $promotionWork->sharing_proof ?? [];
            $sharingProof['share_konten_tasks'] = $tasks;

            $promotionWork->sharing_proof = $sharingProof;
            $promotionWork->save();

            // Mark Step 10 as completed
            $stepProgress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', 10)
                ->first();

            if ($stepProgress && $stepProgress->status !== 'completed') {
                $stepProgress->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }

            // Mark episode and program as promoted so Step 10 shows green checkmark
            if ($promotionWork->episode) {
                $promotionWork->episode->update(['status' => 'promoted']);

                if ($promotionWork->episode->program) {
                    $promotionWork->episode->program->update(['status' => 'promoted']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Share Konten tasks saved successfully',
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

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
