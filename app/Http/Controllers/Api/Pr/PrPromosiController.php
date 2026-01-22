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

            $query = PrPromotionWork::with(['episode.program', 'createdBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Promotion works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
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
}
