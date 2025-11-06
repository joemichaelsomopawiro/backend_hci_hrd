<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Program;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class GeneralAffairsController extends Controller
{
    /**
     * Get all budget requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = BudgetRequest::with(['program', 'requestedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by program
        if ($request->has('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get specific budget request
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request = BudgetRequest::with(['program', 'requestedBy', 'approvedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Approve budget request
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not pending'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'approved_amount' => 'required|numeric|min:0',
            'approval_notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'payment_schedule' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $budgetRequest->update([
            'status' => 'approved',
            'approved_amount' => $request->approved_amount,
            'approval_notes' => $request->approval_notes,
            'payment_method' => $request->payment_method,
            'payment_schedule' => $request->payment_schedule,
            'approved_by' => $user->id,
            'approved_at' => now()
        ]);

        // Notify requester
        $this->notifyRequester($budgetRequest, 'approved');

        return response()->json([
            'success' => true,
            'message' => 'Budget request approved successfully',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'approvedBy'])
        ]);
    }

    /**
     * Reject budget request
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not pending'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
            'rejection_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $budgetRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'rejection_notes' => $request->rejection_notes,
            'rejected_by' => $user->id,
            'rejected_at' => now()
        ]);

        // Notify requester
        $this->notifyRequester($budgetRequest, 'rejected');

        return response()->json([
            'success' => true,
            'message' => 'Budget request rejected',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'rejectedBy'])
        ]);
    }

    /**
     * Process payment
     */
    public function processPayment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not approved'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_receipt' => 'nullable|string',
            'payment_notes' => 'nullable|string',
            'payment_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $budgetRequest->update([
            'status' => 'paid',
            'payment_receipt' => $request->payment_receipt,
            'payment_notes' => $request->payment_notes,
            'payment_date' => $request->payment_date ?? now(),
            'processed_by' => $user->id
        ]);

        // Notify requester
        $this->notifyRequester($budgetRequest, 'paid');

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'approvedBy'])
        ]);
    }

    /**
     * Get budget statistics
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $stats = [
            'total_requests' => BudgetRequest::count(),
            'pending_requests' => BudgetRequest::where('status', 'pending')->count(),
            'approved_requests' => BudgetRequest::where('status', 'approved')->count(),
            'rejected_requests' => BudgetRequest::where('status', 'rejected')->count(),
            'paid_requests' => BudgetRequest::where('status', 'paid')->count(),
            'total_requested_amount' => BudgetRequest::sum('requested_amount'),
            'total_approved_amount' => BudgetRequest::where('status', 'approved')->sum('approved_amount'),
            'total_paid_amount' => BudgetRequest::where('status', 'paid')->sum('approved_amount'),
            'pending_amount' => BudgetRequest::where('status', 'pending')->sum('requested_amount')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get budget requests by program
     */
    public function getByProgram($programId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $requests = BudgetRequest::with(['requestedBy', 'approvedBy'])
            ->where('program_id', $programId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Notify requester about budget request status
     */
    private function notifyRequester($budgetRequest, $status)
    {
        $statusMessages = [
            'approved' => 'Budget request approved',
            'rejected' => 'Budget request rejected',
            'paid' => 'Payment processed'
        ];

        $statusDescriptions = [
            'approved' => "Your budget request for {$budgetRequest->program->name} has been approved",
            'rejected' => "Your budget request for {$budgetRequest->program->name} has been rejected",
            'paid' => "Payment for your budget request has been processed"
        ];

        Notification::create([
            'user_id' => $budgetRequest->requested_by,
            'type' => "budget_request_{$status}",
            'title' => $statusMessages[$status],
            'message' => $statusDescriptions[$status],
            'data' => [
                'budget_request_id' => $budgetRequest->id,
                'program_id' => $budgetRequest->program_id,
                'program_name' => $budgetRequest->program->name,
                'status' => $status
            ]
        ]);
    }
}











