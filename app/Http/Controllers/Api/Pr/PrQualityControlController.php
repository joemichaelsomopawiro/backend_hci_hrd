<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrQualityControlWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrQualityControlController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Quality Control') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $query = PrQualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'QC works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Quality Control') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when pending'], 400);
            }

            $work->markAsInProgress();
            $work->update(['reviewed_by' => $user->id]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'reviewedBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function submitQCForm(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Quality Control') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'qc_checklist' => 'required|array',
                'quality_score' => 'required|integer|min:1|max:100',
                'qc_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrQualityControlWork::findOrFail($id);

            if ($work->reviewed_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $work->update([
                'qc_checklist' => $request->qc_checklist,
                'qc_results' => $request->qc_results,
                'quality_score' => $request->quality_score,
                'qc_notes' => $request->qc_notes,
                'issues_found' => $request->issues_found,
                'improvements_needed' => $request->improvements_needed,
                'status' => 'completed',
                'qc_completed_at' => now()
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC form submitted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Quality Control') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::findOrFail($id);

            if ($work->status !== 'completed') {
                return response()->json(['success' => false, 'message' => 'QC must be completed to approve'], 400);
            }

            $work->markAsApproved();

            // Auto-create Broadcasting work
            \App\Models\PrBroadcastingWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'main_episode'],
                ['status' => 'preparing', 'created_by' => $user->id]
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC approved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Quality Control') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrQualityControlWork::findOrFail($id);

            $work->update([
                'status' => 'rejected',
                'review_notes' => $request->reason
            ]);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC rejected']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
