<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrBroadcastingWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrBroadcastingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. Role: ' . ($user->role ?? 'none')], 403);
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
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            if ($work->status !== 'preparing') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when preparing'], 400);
            }

            $work->update([
                'status' => 'in_progress', // still in progress until upload/publish
                'created_by' => $user->id
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
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
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                // return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $work->update($request->all());

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Broadcasting work updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function uploadYouTube(Request $request, int $id): JsonResponse
    {
        // Legacy or specific action? Frontend calls generic update mostly.
        // But if needed:
        return $this->update($request, $id);
    }

    public function publish(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
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
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'share_facebook'],
                ['status' => 'pending', 'title' => 'Share Episode to Facebook']
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Episode published successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $allowedRoles = ['Broadcasting', 'broadcasting', 'Manager Broadcasting', 'manager_broadcasting', 'Program Manager', 'program_manager', 'Manager Program', 'manager_program', 'Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'distributionmanager'];
            if (!$user || !in_array($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'title' => 'required|string',
                'description' => 'nullable|string',
                'youtube_url' => 'required|url',
                'thumbnail_small_url' => 'nullable|string'
            ]);

            $work = PrBroadcastingWork::findOrFail($id);

            $work->update([
                'title' => $request->title,
                'description' => $request->description,
                'youtube_url' => $request->youtube_url,
                'thumbnail_path' => $request->thumbnail_small_url,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $user->id
            ]);

            // Mark Step 9 as completed
            $stepProgress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
                ->where('workflow_step', 9)
                ->first();

            if ($stepProgress && $stepProgress->status !== 'completed') {
                $stepProgress->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by' => $user->id
                ]);
            }

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
