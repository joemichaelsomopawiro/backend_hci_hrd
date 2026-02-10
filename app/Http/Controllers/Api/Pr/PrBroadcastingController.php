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

            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
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

            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrBroadcastingWork::findOrFail($id);

            if ($work->status !== 'preparing') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when preparing'], 400);
            }

            $work->update([
                'status' => 'preparing', // still preparing until upload
                'created_by' => $user->id
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Broadcasting') {
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

            if (!$user || $user->role !== 'Broadcasting') {
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

    private function extractYouTubeVideoId(string $url): ?string
    {
        preg_match('/[?&]v=([^&]+)/', $url, $matches);
        return $matches[1] ?? null;
    }
}
