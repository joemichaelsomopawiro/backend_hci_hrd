<?php

namespace App\Http\Controllers;

use App\Models\ProgramApproval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProgramApprovalController extends Controller
{
    /**
     * Display a listing of approvals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProgramApproval::with([
                'approvable',
                'requestedBy',
                'reviewedBy',
                'approvedBy',
                'rejectedBy'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by approval type
            if ($request->has('approval_type')) {
                $query->where('approval_type', $request->approval_type);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by requester
            if ($request->has('requested_by')) {
                $query->where('requested_by', $request->requested_by);
            }

            // Filter by approver
            if ($request->has('approved_by')) {
                $query->where('approved_by', $request->approved_by);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $approvals = $query->paginate($request->get('per_page', 15));

            // Add additional info
            $approvals->getCollection()->transform(function ($approval) {
                $approval->approval_type_label = $approval->approval_type_label;
                $approval->priority_label = $approval->priority_label;
                $approval->is_overdue = $approval->isOverdue();
                $approval->is_urgent = $approval->isUrgent();
                return $approval;
            });

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created approval request
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'approvable_id' => 'required|integer',
                'approvable_type' => 'required|string',
                'approval_type' => 'required|in:program_proposal,program_schedule,episode_rundown,production_schedule,schedule_change,schedule_cancellation,deadline_extension',
                'requested_by' => 'required|exists:users,id',
                'request_notes' => 'nullable|string',
                'request_data' => 'nullable|array',
                'current_data' => 'nullable|array',
                'priority' => 'sometimes|in:low,normal,high,urgent',
                'due_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval = ProgramApproval::create(array_merge(
                $request->all(),
                ['requested_at' => now(), 'status' => 'pending']
            ));

            $approval->load(['approvable', 'requestedBy']);

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval request created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating approval request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified approval
     */
    public function show(string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::with([
                'approvable',
                'requestedBy',
                'reviewedBy',
                'approvedBy',
                'rejectedBy'
            ])->findOrFail($id);

            $approval->approval_type_label = $approval->approval_type_label;
            $approval->priority_label = $approval->priority_label;
            $approval->is_overdue = $approval->isOverdue();
            $approval->is_urgent = $approval->isUrgent();

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approval: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified approval
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::findOrFail($id);

            // Cannot update if already approved, rejected, or cancelled
            if (in_array($approval->status, ['approved', 'rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update approval with status: ' . $approval->status
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'request_notes' => 'nullable|string',
                'request_data' => 'nullable|array',
                'priority' => 'sometimes|in:low,normal,high,urgent',
                'due_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval->update($request->all());
            $approval->load(['approvable', 'requestedBy']);

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark approval as reviewed
     */
    public function markAsReviewed(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reviewer_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending approvals can be marked as reviewed'
                ], 422);
            }

            $approval->markAsReviewed($request->reviewer_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval marked as reviewed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking as reviewed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve the approval request
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'approver_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!in_array($approval->status, ['pending', 'reviewed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or reviewed approvals can be approved'
                ], 422);
            }

            $approval->approve($request->approver_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject the approval request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'rejecter_id' => 'required|exists:users,id',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!in_array($approval->status, ['pending', 'reviewed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or reviewed approvals can be rejected'
                ], 422);
            }

            $approval->reject($request->rejecter_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval rejected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the approval request (by requester)
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $approval = ProgramApproval::findOrFail($id);

            if (!in_array($approval->status, ['pending', 'reviewed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or reviewed approvals can be cancelled'
                ], 422);
            }

            $approval->cancel();

            return response()->json([
                'success' => true,
                'data' => $approval,
                'message' => 'Approval cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals
     */
    public function getPending(Request $request): JsonResponse
    {
        try {
            $query = ProgramApproval::with([
                'approvable',
                'requestedBy'
            ])->pending();

            // Filter by approval type
            if ($request->has('approval_type')) {
                $query->where('approval_type', $request->approval_type);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            $approvals = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Pending approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue approvals
     */
    public function getOverdue(Request $request): JsonResponse
    {
        try {
            $query = ProgramApproval::with([
                'approvable',
                'requestedBy'
            ])->overdue();

            $approvals = $query->orderBy('due_date', 'asc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Overdue approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving overdue approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get urgent approvals
     */
    public function getUrgent(Request $request): JsonResponse
    {
        try {
            $query = ProgramApproval::with([
                'approvable',
                'requestedBy'
            ])->urgent();

            $approvals = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Urgent approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving urgent approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approval history
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $query = ProgramApproval::with([
                'approvable',
                'requestedBy',
                'approvedBy',
                'rejectedBy'
            ])->whereIn('status', ['approved', 'rejected', 'cancelled']);

            // Filter by approval type
            if ($request->has('approval_type')) {
                $query->where('approval_type', $request->approval_type);
            }

            $approvals = $query->orderBy('updated_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Approval history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approval history: ' . $e->getMessage()
            ], 500);
        }
    }
}

