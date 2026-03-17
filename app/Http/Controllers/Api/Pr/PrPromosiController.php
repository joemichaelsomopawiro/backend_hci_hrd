<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrPromotionWork;
use App\Models\Notification;
use App\Constants\WorkflowStep;
use App\Services\PrWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\PrActivityLogService;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrEpisode;
use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrDesignGrafisWork;
use App\Constants\Role;

class PrPromosiController extends Controller
{
    protected $activityLogService;

    public function __construct(PrActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // AUTO-SYNC & SELF-HEALING: Ensure all episodes with completed Step 4 have a Promotion Work
            // We use a more careful approach to avoid overwriting existing status/data
            $eligibleEpisodes = PrEpisodeWorkflowProgress::where('workflow_step', 4)
                ->where('status', 'completed')
                ->pluck('episode_id')
                ->unique();

            foreach ($eligibleEpisodes as $episodeId) {
                try {
                    $creativeWork = PrCreativeWork::where('pr_episode_id', $episodeId)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $work = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                    if (!$work) {
                        PrPromotionWork::create([
                            'pr_episode_id' => $episodeId,
                            'work_type' => 'bts_video',
                            'status' => 'planning',
                            'created_by' => $creativeWork ? $creativeWork->created_by : $user->id,
                            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
                            'shooting_notes' => 'Auto-synced from Stage 4 completion'
                        ]);
                    } else {
                        // RECOVERY & UPDATE: If record exists, only update metadata if missing, and RECOVER status if lost
                        $updateData = [];

                        if (!$work->created_by && $creativeWork) {
                            $updateData['created_by'] = $creativeWork->created_by;
                        }

                        // ALWAYS SYNC: If shooting_date or shooting_time differs from creativeWork, update it
                        if ($creativeWork) {
                            $cwDate = $creativeWork->shooting_schedule ? date('Y-m-d', strtotime($creativeWork->shooting_schedule)) : null;
                            $cwTime = $creativeWork->shooting_schedule ? date('H:i:s', strtotime($creativeWork->shooting_schedule)) : null;

                            $pwDate = $work->shooting_date ? $work->shooting_date->format('Y-m-d') : null;
                            $pwTime = $work->shooting_time;

                            if ($cwDate !== $pwDate) {
                                $updateData['shooting_date'] = $cwDate;
                            }
                            if ($cwTime !== $pwTime) {
                                $updateData['shooting_time'] = $cwTime;
                            }
                            if ($creativeWork->shooting_location !== $work->location_data) {
                                $updateData['location_data'] = $creativeWork->shooting_location;
                            }
                        }

                        // RECOVERY: If status was reset to 'planning' but it was actually finished
                        $isFinished = ($work->episode && $work->episode->status === 'promoted') || !empty($work->sharing_proof);
                        if ($isFinished && $work->status === 'planning') {
                            $updateData['status'] = 'completed';
                            if (!$work->completed_at) {
                                $updateData['completed_at'] = now();
                            }
                        }

                        if (!empty($updateData)) {
                            $work->update($updateData);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Sync failed for episode $episodeId: " . $e->getMessage());
                }
            }


            // MAIN QUERY: Only show works that have passed Step 4 (Budget Approval)
            $query = PrPromotionWork::with(['episode.program', 'episode.creativeWork', 'createdBy'])
                ->whereHas('episode.workflowProgress', function ($q) {
                    $q->where('workflow_step', 4)->where('status', 'completed');
                });

            // ROLE-BASED FILTERING: Matching PrProduksiController logic
            // Only allow designated roles to see all, others only see assigned
            $isManager = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER]);
            $isPromosiFull = Role::normalize($user->role) === Role::PROMOTION;

            if (!$isManager && !$isPromosiFull) {
                // If they are specific promotion crew (e.g. from Episode Crew), filter by their assignment
                $query->whereHas('episode.crews', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('work_type') && $request->work_type !== '') {
                $query->where('work_type', $request->work_type);
            }

            // Apply program filter if exists
            if ($request->has('program_id') && $request->program_id !== '') {
                $query->whereHas('episode', function ($q) use ($request) {
                    $q->where('program_id', $request->program_id);
                });
            }

            $works = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Promotion works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
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

            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
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

            // Sync Step 5 progress (Shooting phase transition to Editing/Design)
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Content uploaded successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function shareContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
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

            // Sync Step 5 progress (Shooting phase transition to Editing/Design)
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // Sync Step 10 progress
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 10);

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
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
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
                app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);
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
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            $work->update([
                'status' => 'completed',
                'completion_notes' => request('completion_notes', request('notes', $work->completion_notes))
            ]);

            Log::info("Promotion Work [{$id}] completed. Syncing Step 5 for Episode [{$work->pr_episode_id}]");
            app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // AUTO-CREATE PrEditorPromosiWork when Promotion completes
            $exists = PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->exists();

            if (!$exists) {
                // Find the main_episode editor work (may or may not be ready)
                $mainEditorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                    ->where('work_type', 'main_episode')
                    ->first();

                $editorReady = $mainEditorWork && in_array($mainEditorWork->status, ['pending_qc', 'completed']);

                PrEditorPromosiWork::create([
                    'pr_episode_id' => $work->pr_episode_id,
                    'pr_promotion_work_id' => $work->id,
                    'pr_editor_work_id' => $mainEditorWork ? $mainEditorWork->id : null,
                    'status' => $editorReady ? 'pending' : 'waiting_editor',
                ]);

                Log::info("Auto-created PrEditorPromosiWork for Episode [{$work->pr_episode_id}], status: " . ($editorReady ? 'pending' : 'waiting_editor'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Work marked as completed.',
                'data' => $work,
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

            $episodes = PrEpisode::with(['program', 'promotionWork', 'broadcastingWork', 'editorPromosiWork'])
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
            $editorPromosi = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
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
            $finalize = $request->boolean('finalize', true); // Default to true if not provided (old behavior)
            // Merge into sharing_proof under dedicated key to avoid overwriting other data
            $sharingProof = $promotionWork->sharing_proof ?? [];
            $sharingProof['share_konten_tasks'] = $tasks;

            $promotionWork->sharing_proof = $sharingProof;
            $promotionWork->save();

            if (!$finalize) {
                return response()->json([
                    'success' => true,
                    'message' => 'Share Konten tasks saved automatically',
                    'data' => $tasks
                ]);
            }

            // Mark Step 10 as completed
            $stepProgress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', 10)
                ->first();

            if ($stepProgress && $stepProgress->status !== 'completed') {
                $stepProgress->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

                // Log activity
                $this->activityLogService->logEpisodeActivity(
                    $promotionWork->episode,
                    'share_konten_finish',
                    "Share Konten tasks completed.",
                    ['step' => 10, 'status' => 'completed'],
                    $promotionWork->id
                );
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
}
