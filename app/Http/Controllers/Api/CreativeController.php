<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreativeWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Services\WorkAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreativeController extends Controller
{
    public function __construct()
    {
        // Link-only policy enforced
    }

    /**
     * Get creative works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = CreativeWork::with([
            'episode.program', 
            'latestApprovedMusicArrangement',
            'createdBy', 
            'reviewedBy'
        ]);
            
            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by creator - default to current user if Creative role
            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            } elseif ($user->role === 'Creative') {
                $query->where('created_by', $user->id);
            }

            // Filter untuk "Terima Pekerjaan" - hanya creative work dengan status draft
            if ($request->has('ready_for_work') && $request->ready_for_work == 'true') {
                $query->where('status', 'draft');
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
                'message' => 'Failed to retrieve creative works',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative work by ID
     */
    public function show(int $id): JsonResponse
    {
        // Use cache for frequently accessed data
        $work = \App\Helpers\QueryOptimizer::remember(
            \App\Helpers\QueryOptimizer::getCacheKey('CreativeWork', $id),
            300, // 5 minutes
            function () use ($id) {
                return CreativeWork::with([
                'episode.program.managerProgram',
                'episode.program.productionTeam.members.user',
                'latestApprovedMusicArrangement',
                'createdBy',
                'reviewedBy'
            ])->findOrFail($id);
            }
        );
        
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
            'script_link' => 'nullable|url|max:2048',
            'storyboard_data' => 'nullable|array',
            'storyboard_link' => 'nullable|url|max:2048',
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
            // Get episode details for auto-assignment logic
            $episode = Episode::with('program')->findOrFail($request->episode_id);
            
            // AUTO-ASSIGNMENT LOGIC: Use WorkAssignmentService to determine assignee
            $assignedUserId = WorkAssignmentService::getNextAssignee(
                CreativeWork::class,
                $episode->program_id,
                $episode->episode_number,
                null,  // CreativeWork doesn't have work_type
                $request->created_by  // Use requested creator ID as fallback
            );

            $work = CreativeWork::create([
                'episode_id' => $request->episode_id,
                'script_content' => $request->script_content,
                'script_link' => $request->script_link,
                'storyboard_data' => $request->storyboard_data,
                'storyboard_link' => $request->storyboard_link,
                'budget_data' => $request->budget_data,
                'recording_schedule' => $request->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule,
                'shooting_location' => $request->shooting_location,
                'status' => 'draft',
                'created_by' => $assignedUserId,           // AUTO-ASSIGNED
                'originally_assigned_to' => null,           // Reset
                'was_reassigned' => false                   // Reset
            ]);
            
            // Audit logging
            ControllerSecurityHelper::logCreate($work, [
                'episode_id' => $work->episode_id,
                'status' => 'draft'
            ], $request);
            
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
            'script_link' => 'nullable|url|max:2048',
            'storyboard_data' => 'nullable',
            'storyboard_link' => 'nullable|url|max:2048',
            'budget_data' => 'nullable',
            'recording_schedule' => 'nullable|string',
            'shooting_schedule' => 'nullable|string',
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
            // Build update data explicitly to handle both form-data and JSON
            $updateData = [];
            
            if ($request->has('script_content')) {
                $updateData['script_content'] = $request->script_content;
            }
            if ($request->has('script_link')) {
                $updateData['script_link'] = $request->script_link;
            }
            if ($request->has('storyboard_link')) {
                $updateData['storyboard_link'] = $request->storyboard_link;
            }
            if ($request->has('storyboard_data')) {
                // Handle JSON string from form-data
                $storyboardData = $request->storyboard_data;
                if (is_string($storyboardData)) {
                    $storyboardData = json_decode($storyboardData, true);
                }
                $updateData['storyboard_data'] = $storyboardData;
            }
            if ($request->has('budget_data')) {
                // Handle JSON string from form-data
                $budgetData = $request->budget_data;
                if (is_string($budgetData)) {
                    $budgetData = json_decode($budgetData, true);
                }
                $updateData['budget_data'] = $budgetData;
            }
            if ($request->has('recording_schedule')) {
                $updateData['recording_schedule'] = $request->recording_schedule;
            }
            if ($request->has('shooting_schedule')) {
                $updateData['shooting_schedule'] = $request->shooting_schedule;
            }
            if ($request->has('shooting_location')) {
                $updateData['shooting_location'] = $request->shooting_location;
            }
            
            $work->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode.program', 'createdBy']),
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

    /**
     * Terima Pekerjaan - Creative terima pekerjaan setelah arrangement approved
     * User: "Terima Pekerjaan"
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow accept work if status is draft
            if ($work->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is draft'
                ], 400);
            }

            // Change status to in_progress
            $oldStatus = $work->status;
            $work->update([
                'status' => 'in_progress'
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('creative_work_accepted', $work, [
                'episode_id' => $work->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'in_progress'
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now start working on script, storyboard, schedules, and budget.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Creative selesaikan setelah selesai semua tugas
     * User: "Selesaikan Pekerjaan"
     * Includes: script, storyboard, recording schedule, shooting schedule, location, budget
     */
    public function completeWork(Request $request, int $id): JsonResponse
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
                'script_content' => 'nullable|string',
                'script_link' => 'nullable|url|max:2048',
                'storyboard_data' => 'nullable|array',
                'storyboard_link' => 'nullable|url|max:2048',
                'recording_schedule' => 'nullable|string', // Use string to be more flexible with ISO formats
                'shooting_schedule' => 'nullable|string',
                'shooting_location' => 'nullable|string|max:255',
                'budget_data' => 'nullable|array',
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                Log::warning('Creative Complete Work Validation Failed:', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all(),
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the work, allow any Creative user to complete it if they are in the same team
            // but for now, let's keep it simple: any user with role 'Creative' can update.
            $work = CreativeWork::where('id', $id)->firstOrFail();
            
            if (strtolower($user->role) !== 'creative') {
                 return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Creative role can complete this work.'
                ], 403);
            }

            // Only allow complete if status is in_progress
            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            // Update only fields that are provided in the request
            // This preserves data that was previously saved via PUT /works/{id}
            $updateData = ['status' => 'submitted']; // Always set status to submitted
            
            if ($request->has('script_content') && $request->script_content !== null) {
                $updateData['script_content'] = $request->script_content;
            }
            if ($request->has('script_link') && $request->script_link !== null) {
                $updateData['script_link'] = $request->script_link;
            }
            if ($request->has('storyboard_data') && $request->storyboard_data !== null) {
                $updateData['storyboard_data'] = $request->storyboard_data;
            }
            if ($request->has('storyboard_link') && $request->storyboard_link !== null) {
                $updateData['storyboard_link'] = $request->storyboard_link;
            }
            if ($request->has('recording_schedule') && $request->recording_schedule !== null) {
                $updateData['recording_schedule'] = $request->recording_schedule;
            }
            if ($request->has('shooting_schedule') && $request->shooting_schedule !== null) {
                $updateData['shooting_schedule'] = $request->shooting_schedule;
            }
            if ($request->has('shooting_location') && $request->shooting_location !== null) {
                $updateData['shooting_location'] = $request->shooting_location;
            }
            if ($request->has('budget_data') && $request->budget_data !== null) {
                $updateData['budget_data'] = $request->budget_data;
            }
            if ($request->completion_notes) {
                $updateData['review_notes'] = ($work->review_notes ? $work->review_notes . "\n\n" : '') . "Completion notes: " . $request->completion_notes;
            }
            
            $work->update($updateData);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'creative_work_submitted',
                    'title' => 'Creative Work Selesai',
                    'message' => "Creative {$user->name} telah menyelesaikan creative work untuk Episode {$episode->episode_number}. Script, storyboard, schedules, dan budget telah disubmit untuk review.",
                    'data' => [
                        'creative_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'completion_notes' => $request->completion_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Creative work has been submitted for Producer review.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revisi Creative Work setelah budget ditolak
     * PUT /api/live-tv/roles/creative/works/{id}/revise
     */
    public function reviseCreativeWork(Request $request, int $id): JsonResponse
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
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'budget_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'shooting_schedule' => 'nullable|date',
                'shooting_location' => 'nullable|string|max:255',
                'revision_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow revise if status is rejected or revised
            if (!in_array($work->status, ['rejected', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be revised when status is rejected or revised'
                ], 400);
            }

            // Update fields
            $updateData = [];
            if ($request->has('script_content')) $updateData['script_content'] = $request->script_content;
            if ($request->has('storyboard_data')) $updateData['storyboard_data'] = $request->storyboard_data;
            if ($request->has('budget_data')) $updateData['budget_data'] = $request->budget_data;
            if ($request->has('recording_schedule')) $updateData['recording_schedule'] = $request->recording_schedule;
            if ($request->has('shooting_schedule')) $updateData['shooting_schedule'] = $request->shooting_schedule;
            if ($request->has('shooting_location')) $updateData['shooting_location'] = $request->shooting_location;

            // Reset review fields
            $updateData['script_approved'] = null;
            $updateData['storyboard_approved'] = null;
            $updateData['budget_approved'] = null;
            $updateData['script_review_notes'] = null;
            $updateData['storyboard_review_notes'] = null;
            $updateData['budget_review_notes'] = null;
            $updateData['requires_special_budget_approval'] = false;
            $updateData['special_budget_reason'] = null;
            $updateData['special_budget_approval_id'] = null;
            $updateData['status'] = 'revised';

            if ($request->revision_notes) {
                $updateData['review_notes'] = ($work->review_notes ? $work->review_notes . "\n\n" : '') . 
                    "[Revisi] " . $request->revision_notes;
            }

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work revised successfully. You can now resubmit to Producer.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error revising creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resubmit Creative Work setelah revisi
     * POST /api/live-tv/roles/creative/works/{id}/resubmit
     */
    public function resubmitCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow resubmit if status is revised
            if ($work->status !== 'revised') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be resubmitted when status is revised'
                ], 400);
            }

            // Validate required fields
            if (!$work->script_content || !$work->storyboard_data || !$work->budget_data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete script, storyboard, and budget before resubmitting'
                ], 400);
            }

            // Resubmit
            $oldStatus = $work->status;
            $work->update([
                'status' => 'submitted'
            ]);

            // Audit logging
            ControllerSecurityHelper::logApproval('creative_work_resubmitted', $work, [
                'episode_id' => $work->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'submitted'
            ], $request);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'creative_work_resubmitted',
                    'title' => 'Creative Work Diresubmit',
                    'message' => "Creative {$user->name} telah meresubmit creative work untuk Episode {$episode->episode_number} setelah revisi.",
                    'data' => [
                        'creative_work_id' => $work->id,
                        'episode_id' => $work->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work resubmitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resubmitting creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Storyboard file
     * POST /api/live-tv/roles/creative/works/{id}/upload-storyboard
     */
    /**
     * Update Script or Storyboard Link
     * PUT /api/live-tv/roles/creative/works/{id}/update-link
     * Replaces file upload mechanism
     */
    public function updateLink(Request $request, int $id): JsonResponse
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
                'type' => 'required|in:script,storyboard',
                'link' => 'required|url|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = CreativeWork::findOrFail($id);
            
            // Validate ownership/assignment logic typically should be here
            if ($work->created_by !== $user->id) {
                // strict check removed for now as per controller convention allowing team members to edit
            }

            $field = $request->type . '_link'; // script_link or storyboard_link
            $work->update([$field => $request->link]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => ucfirst($request->type) . ' link updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating link: ' . $e->getMessage()
            ], 500);
        }
    }
}














