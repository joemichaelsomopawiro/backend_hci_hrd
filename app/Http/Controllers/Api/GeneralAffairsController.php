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
use Illuminate\Support\Facades\Schema;

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

        // Notify requester (Producer)
        $this->notifyRequester($budgetRequest, 'paid');

        // Notify Producer bahwa dana telah diberikan
        if ($budgetRequest->requestedBy) {
            Notification::create([
                'user_id' => $budgetRequest->requested_by,
                'type' => 'fund_released',
                'title' => 'Dana Telah Diberikan',
                'message' => "Dana sebesar Rp " . number_format($budgetRequest->approved_amount ?? $budgetRequest->requested_amount, 0, ',', '.') . " untuk {$budgetRequest->title} telah diberikan oleh General Affairs.",
                'data' => [
                    'budget_request_id' => $budgetRequest->id,
                    'program_id' => $budgetRequest->program_id,
                    'amount' => $budgetRequest->approved_amount ?? $budgetRequest->requested_amount,
                    'payment_receipt' => $request->payment_receipt,
                    'payment_date' => $request->payment_date ?? now()
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully. Producer has been notified.',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'approvedBy'])
        ]);
    }

    /**
     * Get budget requests from Creative Work (permohonan dana setelah Producer approve)
     * GET /api/live-tv/general-affairs/budget-requests/from-creative-work
     */
    public function getCreativeWorkBudgetRequests(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BudgetRequest::where('request_type', 'creative_work')
                ->with(['program', 'requestedBy', 'approvedBy', 'processedBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Default: show pending requests
                $query->where('status', 'pending');
            }

            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $requests = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Creative work budget requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve budget requests',
                'error' => $e->getMessage()
            ], 500);
        }
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

        // Check if requested_amount column exists
        $hasRequestedAmount = \Schema::hasColumn('budget_requests', 'requested_amount');
        
        $stats = [
            'total_requests' => BudgetRequest::count(),
            'pending_requests' => BudgetRequest::where('status', 'pending')->count(),
            'approved_requests' => BudgetRequest::where('status', 'approved')->count(),
            'rejected_requests' => BudgetRequest::where('status', 'rejected')->count(),
            'paid_requests' => BudgetRequest::where('status', 'paid')->count(),
        ];
        
        if ($hasRequestedAmount) {
            $stats['total_requested_amount'] = BudgetRequest::sum('requested_amount');
            $stats['total_approved_amount'] = BudgetRequest::where('status', 'approved')->sum('approved_amount');
            $stats['total_paid_amount'] = BudgetRequest::where('status', 'paid')->sum('approved_amount');
            $stats['pending_amount'] = BudgetRequest::where('status', 'pending')->sum('requested_amount');
        } else {
            // Fallback if column doesn't exist
            $stats['total_requested_amount'] = 0;
            $stats['total_approved_amount'] = 0;
            $stats['total_paid_amount'] = 0;
            $stats['pending_amount'] = 0;
            \Log::warning('GeneralAffairsController::statistics - requested_amount column not found in budget_requests table');
        }

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











