<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrCreativeWork;
use App\Models\PrEpisode;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrCreativeController extends Controller
{
    /**
     * Get creative works for current user (Creative role)
     * GET /api/pr/creative/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = PrCreativeWork::with(['episode.program', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by assigned user
            if ($request->boolean('my_works', false)) {
                $query->where('created_by', $user->id);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving creative works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific creative work
     * GET /api/pr/creative/works/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $work = PrCreativeWork::with([
                'episode.program',
                'createdBy',
                'reviewedBy',
                'scriptApprovedBy',
                'budgetApprovedBy',
                'specialBudgetApprover'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create creative work for PR episode
     * POST /api/pr/creative/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'pr_episode_id' => 'required|exists:pr_episodes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if creative work already exists for this episode
            $existing = PrCreativeWork::where('pr_episode_id', $request->pr_episode_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work already exists for this episode',
                    'data' => $existing
                ], 400);
            }

            $work = PrCreativeWork::create([
                'pr_episode_id' => $request->pr_episode_id,
                'status' => 'draft',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Creative work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept work - Creative accepts assignment
     * POST /api/pr/creative/works/{id}/accept-work
     */
    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            if ($work->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is draft'
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now start working on script, storyboard, and budget.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update creative work (script, storyboard, budget, schedules)
     * PUT /api/pr/creative/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if (!in_array($work->status, ['draft', 'in_progress', 'rejected', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work cannot be updated in current status: ' . $work->status
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'budget_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'shooting_schedule' => 'nullable|date|after:today',
                'shooting_location' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'script_content',
                'storyboard_data',
                'budget_data',
                'recording_schedule',
                'shooting_schedule',
                'shooting_location'
            ]);

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete and submit work to Producer for review
     * POST /api/pr/creative/works/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::with('episode.program.producer')->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if (!in_array($work->status, ['in_progress', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be submitted when in_progress or revised'
                ], 400);
            }

            // Validate required fields
            if (!$work->script_content || !$work->budget_data || !$work->shooting_schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete required fields: script_content, budget_data, and shooting_schedule before submitting',
                    'missing' => [
                        'script_content' => !$work->script_content,
                        'budget_data' => !$work->budget_data,
                        'shooting_schedule' => !$work->shooting_schedule
                    ]
                ], 400);
            }

            $work->update([
                'status' => 'submitted',
                'reviewed_at' => null, // Reset review
                'review_notes' => null
            ]);

            // Notify Producer
            $producer = $work->episode->program->producer ?? null;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'pr_creative_work_submitted',
                    'title' => 'Creative Work Submitted',
                    'message' => "Creative work for PR Episode {$work->episode->episode_number} has been submitted for review.",
                    'data' => [
                        'creative_work_id' => $work->id,
                        'pr_episode_id' => $work->pr_episode_id,
                        'pr_program_id' => $work->episode->program_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work submitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting creative work: ' . $e->getMessage()
            ], 500);
        }
    }
}
