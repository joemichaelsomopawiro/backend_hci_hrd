<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreativeWork;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CreativeController extends Controller
{
    /**
     * Get creative works
     */
    public function index(Request $request): JsonResponse
    {
        $query = CreativeWork::with(['episode', 'createdBy', 'reviewedBy']);
        
        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        
        $works = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $works,
            'message' => 'Creative works retrieved successfully'
        ]);
    }

    /**
     * Get creative work by ID
     */
    public function show(int $id): JsonResponse
    {
        $work = CreativeWork::with(['episode', 'createdBy', 'reviewedBy'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $work,
            'message' => 'Creative work retrieved successfully'
        ]);
    }

    /**
     * Create creative work
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'script_content' => 'nullable|string',
            'storyboard_data' => 'nullable|array',
            'budget_data' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
            'shooting_schedule' => 'nullable|date',
            'shooting_location' => 'nullable|string|max:255',
            'created_by' => 'required|exists:users,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work = CreativeWork::create([
                'episode_id' => $request->episode_id,
                'script_content' => $request->script_content,
                'storyboard_data' => $request->storyboard_data,
                'budget_data' => $request->budget_data,
                'recording_schedule' => $request->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule,
                'shooting_location' => $request->shooting_location,
                'status' => 'draft',
                'created_by' => $request->created_by
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update creative work
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'script_content' => 'nullable|string',
            'storyboard_data' => 'nullable|array',
            'budget_data' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
            'shooting_schedule' => 'nullable|date',
            'shooting_location' => 'nullable|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit creative work for review
     */
    public function submit(int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft works can be submitted'
            ], 400);
        }
        
        try {
            $work->submitForReview();
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work submitted for review successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve creative work
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be approved'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->approve(auth()->id(), $request->review_notes);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject creative work
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be rejected'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->reject(auth()->id(), $request->rejection_reason);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative works by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $works = CreativeWork::where('episode_id', $episodeId)
                ->with(['createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works by episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative works by episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative works by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        try {
            $works = CreativeWork::where('status', $status)
                ->with(['episode', 'createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works by status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative works by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget summary
     */
    public function getBudgetSummary(int $id): JsonResponse
    {
        try {
            $work = CreativeWork::findOrFail($id);
            $budgetSummary = [
                'total_budget' => $work->total_budget,
                'formatted_budget' => 'Rp ' . number_format($work->total_budget, 0, ',', '.'),
                'budget_data' => $work->formatted_budget_data
            ];
            
            return response()->json([
                'success' => true,
                'data' => $budgetSummary,
                'message' => 'Budget summary retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get budget summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














