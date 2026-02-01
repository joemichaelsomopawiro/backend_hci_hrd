<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Notification;
use App\Models\User;
use App\Helpers\QueryOptimizer;
use App\Helpers\ControllerSecurityHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ProgramProposalController
 * 
 * Handles program proposal workflow for Music Program System.
 * Uses the Program model with status='proposal' as the proposal state.
 * 
 * Workflow:
 * 1. User creates program with status='draft' or 'proposal'
 * 2. User submits proposal -> status='proposal_submitted'
 * 3. Manager Program reviews:
 *    - Approve -> status='approved', auto-generate 52 episodes
 *    - Reject -> status='rejected'
 *    - Request Revision -> status='revision_requested'
 * 4. If revision requested, user updates and resubmits
 */
class ProgramProposalController extends Controller
{
    /**
     * Get all program proposals (Music Program only)
     * GET /api/live-tv/proposals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Build query for proposals (status = draft, proposal, proposal_submitted, revision_requested)
            $query = Program::musik()
                ->whereIn('status', ['draft', 'proposal', 'proposal_submitted', 'revision_requested', 'rejected'])
                ->with(['managerProgram', 'submittedBy', 'approvedBy', 'rejectedBy', 'productionTeam']);
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by manager
            if ($request->has('manager_program_id')) {
                $query->where('manager_program_id', $request->manager_program_id);
            }
            
            // Filter by user's own proposals (for non-managers)
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Administrator'])) {
                $query->where('submitted_by', $user->id);
            }
            
            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $proposals = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $proposals,
                'message' => 'Program proposals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving proposals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving proposals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new program proposal
     * POST /api/live-tv/proposals
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:2000',
                'category' => 'required|in:musik,live_tv,entertainment',
                'start_date' => 'required|date|after:today',
                'air_time' => 'nullable|date_format:H:i',
                'duration_minutes' => 'nullable|integer|min:15|max:180',
                'broadcast_channel' => 'nullable|string|max:100',
                'target_views_per_episode' => 'nullable|integer|min:0',
                'budget_amount' => 'nullable|numeric|min:0',
                'budget_notes' => 'nullable|string|max:1000',
                'proposal_file_link' => 'nullable|url|max:2048',
                'submission_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create program as draft proposal
            $program = Program::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'status' => 'draft',
                'start_date' => $request->start_date,
                'air_time' => $request->air_time ?? '20:00',
                'duration_minutes' => $request->duration_minutes ?? 60,
                'broadcast_channel' => $request->broadcast_channel,
                'target_views_per_episode' => $request->target_views_per_episode ?? 0,
                'budget_amount' => $request->budget_amount,
                'budget_notes' => $request->budget_notes,
                'proposal_file_link' => $request->proposal_file_link,
                'submission_notes' => $request->submission_notes,
                'submitted_by' => $user->id
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_created', $program, [
                'created_by' => $user->id,
                'proposal_name' => $program->name
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $program->load(['submittedBy']),
                'message' => 'Program proposal created successfully. Submit it when ready for review.'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program proposal by ID
     * GET /api/live-tv/proposals/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $program = Program::musik()
                ->with(['managerProgram', 'submittedBy', 'approvedBy', 'rejectedBy', 'productionTeam', 'episodes'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program proposal retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Proposal not found'
            ], 404);
        }
    }

    /**
     * Update program proposal
     * PUT /api/live-tv/proposals/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $program = Program::musik()->findOrFail($id);
            
            // Only allow updates for draft or revision_requested status
            if (!in_array($program->status, ['draft', 'revision_requested'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft or revision-requested proposals can be updated'
                ], 400);
            }
            
            // Only creator or admin can update
            if ($program->submitted_by !== $user->id && !in_array($user->role, ['Administrator', 'Manager Program'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only update your own proposals'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:2000',
                'category' => 'sometimes|in:musik,live_tv,entertainment',
                'start_date' => 'sometimes|date|after:today',
                'air_time' => 'nullable|date_format:H:i',
                'duration_minutes' => 'nullable|integer|min:15|max:180',
                'broadcast_channel' => 'nullable|string|max:100',
                'target_views_per_episode' => 'nullable|integer|min:0',
                'budget_amount' => 'nullable|numeric|min:0',
                'budget_notes' => 'nullable|string|max:1000',
                'proposal_file_link' => 'nullable|url|max:2048',
                'submission_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update($request->only([
                'name', 'description', 'category', 'start_date', 'air_time',
                'duration_minutes', 'broadcast_channel', 'target_views_per_episode',
                'budget_amount', 'budget_notes', 'proposal_file_link', 'submission_notes'
            ]));

            // Audit logging
            ControllerSecurityHelper::logUpdate($program, [], $request->all(), $request);

            return response()->json([
                'success' => true,
                'data' => $program->fresh(['submittedBy']),
                'message' => 'Program proposal updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program proposal
     * DELETE /api/live-tv/proposals/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $program = Program::musik()->findOrFail($id);
            
            // Only allow deletion for draft status
            if ($program->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft proposals can be deleted'
                ], 400);
            }
            
            // Only creator or admin can delete
            if ($program->submitted_by !== $user->id && !in_array($user->role, ['Administrator'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only delete your own proposals'
                ], 403);
            }

            $program->delete();

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_deleted', $program, [
                'deleted_by' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Program proposal deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program proposal for review
     * POST /api/live-tv/proposals/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $program = Program::musik()->findOrFail($id);
            
            // Only allow submission for draft or revision_requested status
            if (!in_array($program->status, ['draft', 'revision_requested'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft or revision-requested proposals can be submitted'
                ], 400);
            }
            
            // Only creator can submit
            if ($program->submitted_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only submit your own proposals'
                ], 403);
            }

            // Update status to submitted
            $program->update([
                'status' => 'proposal_submitted',
                'submitted_at' => now()
            ]);

            // Notify all Manager Programs
            $managers = User::whereIn('role', ['Manager Program', 'Program Manager', 'managerprogram'])
                ->where('is_active', true)
                ->get();

            foreach ($managers as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'type' => 'proposal_submitted',
                    'title' => 'Proposal Program Musik Baru',
                    'message' => "Proposal '{$program->name}' telah diajukan oleh {$user->name} untuk direview.",
                    'data' => [
                        'program_id' => $program->id,
                        'program_name' => $program->name,
                        'submitted_by' => $user->id,
                        'submitted_by_name' => $user->name
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_submitted', $program, [
                'submitted_by' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $program->fresh(['submittedBy']),
                'message' => 'Proposal submitted successfully. Manager Program will review your proposal.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error submitting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program proposal (Manager Program only)
     * POST /api/live-tv/proposals/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only Manager Program can approve
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can approve proposals'
                ], 403);
            }

            $program = Program::musik()->findOrFail($id);
            
            // Only allow approval for submitted proposals
            if ($program->status !== 'proposal_submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted proposals can be approved'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'production_team_id' => 'nullable|exists:production_teams,id',
                'approval_notes' => 'nullable|string|max:2000',
                'budget_approved' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update program to approved status
            $program->update([
                'status' => 'approved',
                'manager_program_id' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'production_team_id' => $request->production_team_id,
                'budget_approved' => $request->budget_approved ?? false,
                'budget_approved_by' => $request->budget_approved ? $user->id : null,
                'budget_approved_at' => $request->budget_approved ? now() : null
            ]);

            // Auto-generate 52 episodes
            $program->generateEpisodes();

            // Notify the proposal submitter
            Notification::create([
                'user_id' => $program->submitted_by,
                'type' => 'proposal_approved',
                'title' => 'Proposal Program Disetujui!',
                'message' => "Selamat! Proposal '{$program->name}' telah disetujui oleh {$user->name}. 52 episode telah dibuat otomatis.",
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'approved_by' => $user->id,
                    'approved_by_name' => $user->name,
                    'episodes_generated' => 52
                ]
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_approved', $program, [
                'approved_by' => $user->id,
                'episodes_generated' => 52
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $program->fresh(['submittedBy', 'approvedBy', 'productionTeam', 'episodes']),
                'message' => 'Proposal approved successfully. 52 weekly episodes have been generated.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error approving proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error approving proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject program proposal (Manager Program only)
     * POST /api/live-tv/proposals/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only Manager Program can reject
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can reject proposals'
                ], 403);
            }

            $program = Program::musik()->findOrFail($id);
            
            // Only allow rejection for submitted proposals
            if ($program->status !== 'proposal_submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted proposals can be rejected'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rejection notes are required',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update program to rejected status
            $program->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_notes' => $request->rejection_notes
            ]);

            // Notify the proposal submitter
            Notification::create([
                'user_id' => $program->submitted_by,
                'type' => 'proposal_rejected',
                'title' => 'Proposal Program Ditolak',
                'message' => "Proposal '{$program->name}' ditolak oleh {$user->name}. Alasan: {$request->rejection_notes}",
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'rejected_by' => $user->id,
                    'rejected_by_name' => $user->name,
                    'rejection_notes' => $request->rejection_notes
                ]
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_rejected', $program, [
                'rejected_by' => $user->id,
                'rejection_notes' => $request->rejection_notes
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $program->fresh(['submittedBy', 'rejectedBy']),
                'message' => 'Proposal rejected. Submitter has been notified.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error rejecting proposal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request revision for program proposal (Manager Program only)
     * POST /api/live-tv/proposals/{id}/request-revision
     */
    public function requestRevision(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only Manager Program can request revision
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can request revisions'
                ], 403);
            }

            $program = Program::musik()->findOrFail($id);
            
            // Only allow revision request for submitted proposals
            if ($program->status !== 'proposal_submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted proposals can be sent back for revision'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'revision_notes' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Revision notes are required',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update program to revision_requested status
            $program->update([
                'status' => 'revision_requested',
                'rejection_notes' => $request->revision_notes // Reuse rejection_notes field for revision notes
            ]);

            // Notify the proposal submitter
            Notification::create([
                'user_id' => $program->submitted_by,
                'type' => 'proposal_revision_requested',
                'title' => 'Proposal Perlu Revisi',
                'message' => "Proposal '{$program->name}' perlu direvisi. Catatan dari {$user->name}: {$request->revision_notes}",
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'requested_by' => $user->id,
                    'requested_by_name' => $user->name,
                    'revision_notes' => $request->revision_notes
                ]
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('proposal_revision_requested', $program, [
                'requested_by' => $user->id,
                'revision_notes' => $request->revision_notes
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $program->fresh(['submittedBy']),
                'message' => 'Revision requested. Submitter has been notified to update the proposal.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error requesting revision: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error requesting revision: ' . $e->getMessage()
            ], 500);
        }
    }
}
