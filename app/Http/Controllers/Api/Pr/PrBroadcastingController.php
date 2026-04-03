<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrBroadcastingWork;
use App\Models\Notification;
use App\Models\PrEpisode;
use App\Services\PrWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\PrActivityLogService;
use App\Constants\Role;

class PrBroadcastingController extends Controller
{
    protected $activityLogService;

    public function __construct(PrActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Check if user is authorized for broadcasting actions
     */
    private function authorizeBroadcasting()
    {
        $user = Auth::user();
        if (!$user) return false;

        return Role::inArray($user->role, [
            Role::BROADCASTING,
            Role::PROGRAM_MANAGER,
            Role::DISTRIBUTION_MANAGER,
            Role::PRODUCER
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // Auto-sync: Find episodes that have finished Manager Distribusi QC (completed)
            // but don't have a broadcasting work yet.
            $completedQcEpisodeIds = \App\Models\PrManagerDistribusiQcWork::where('status', 'completed')
                ->pluck('pr_episode_id');

            // Also check episodes with status 'step_6_completed' (set by checkStep6Completion)
            $step6Episodes = \App\Models\PrEpisode::whereIn('status', ['step_6_completed', 'broadcasting', 'completed'])
                ->pluck('id');

            $allEligibleEpisodeIds = $completedQcEpisodeIds->merge($step6Episodes)->unique();

            foreach ($allEligibleEpisodeIds as $episodeId) {
                // Create broadcasting work if not exists
                PrBroadcastingWork::firstOrCreate(
                    ['pr_episode_id' => $episodeId, 'work_type' => 'main_episode'],
                    ['status' => 'preparing']
                );
            }

            $query = PrBroadcastingWork::with(['episode.program', 'createdBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Broadcasting works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            if ($work->status !== 'preparing') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when preparing'], 400);
            }

            $work->update([
                'status' => 'in_progress', // still in progress until upload/publish
                'created_by' => Auth::id()
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::with([
                'episode.program',
                'createdBy',
                'episode.editorWork',
                'episode.qualityControlWork'
            ])->findOrFail($id);

            $designLocations = $work->episode->qualityControlWork
                ? ($work->episode->qualityControlWork->design_grafis_file_locations ?? [])
                : [];
            $work->thumbnail_link = $designLocations['youtube_thumbnail'] ?? null;
            $work->material_link = $work->episode->editorWork
                ? ($work->episode->editorWork->file_path ?? null)
                : null;

            return response()->json(['success' => true, 'data' => $work]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            // Allow manager to update any work, but broadcasting staff only their own in-progress work
            $user = Auth::user();
            $isManager = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER]);
            
            if (!$isManager && $work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'You are not assigned to this work.'], 403);
            }

            $work->update($request->all());

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Broadcasting work updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function uploadYouTube(Request $request, int $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    public function publish(int $id): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            if (!$work->youtube_url && !$work->website_url) {
                return response()->json(['success' => false, 'message' => 'Please upload to YouTube or website before publishing'], 400);
            }

            $work->markAsPublished();

            // Notify Manager Program
            $managerProgram = $work->episode->program->managerProgram ?? null;
            if ($managerProgram) {
                Notification::create([
                    'user_id' => $managerProgram->id,
                    'type' => 'pr_episode_published',
                    'title' => 'Episode Published',
                    'message' => "PR Episode {$work->episode->episode_number} has been published.",
                    'data' => ['broadcasting_work_id' => $work->id, 'youtube_url' => $work->youtube_url]
                ]);
            }

            // Auto-create Promotion work for sharing if not exists
            \App\Models\PrPromotionWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'bts_video'],
                ['status' => 'planning', 'shooting_notes' => 'Auto-created after publishing']
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Episode published successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        try {
            if (!$this->authorizeBroadcasting()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'title' => 'required|string',
                'description' => 'required|string',
                'youtube_url' => 'required|url',
                'thumbnail_small_url' => 'nullable|string',
                'tags' => 'nullable|string',
                'jetstream_url' => 'required|url',
                'visibility' => 'nullable|string',
                'playlist' => 'nullable|string'
            ]);

            $work = PrBroadcastingWork::findOrFail($id);

            $meta = is_array($work->metadata) ? $work->metadata : [];
            $meta['tags'] = $request->tags;
            $meta['jetstream_url'] = $request->jetstream_url;
            $meta['visibility'] = $request->visibility;
            $meta['playlist'] = $request->playlist;

            $work->update([
                'title' => $request->title,
                'description' => $request->description,
                'youtube_url' => $request->youtube_url,
                'thumbnail_path' => $request->thumbnail_small_url,
                'metadata' => $meta,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => Auth::id()
            ]);

            // Mark Step 9 as completed
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 9);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'broadcasting_finish',
                "Episode published to YouTube: {$request->youtube_url}",
                ['step' => 9, 'youtube_url' => $request->youtube_url]
            );

            // Mark episode as broadcasting_complete so Step 9 shows green checkmark in progress banner
            $work->episode->update(['status' => 'broadcasting_complete']);

            // Also update the program status so ProgramProgressBanner shows Step 9 as green
            if ($work->episode->program) {
                $work->episode->program->update(['status' => 'broadcasting_complete']);
            }

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Pekerjaan selesai, video tersimpan']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function extractYouTubeVideoId(string $url): ?string
    {
        preg_match('/[?&]v=([^&]+)/', $url, $matches);
        return $matches[1] ?? null;
    }
}
