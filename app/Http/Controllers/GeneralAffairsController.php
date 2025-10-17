<?php

namespace App\Http\Controllers;

use App\Models\GeneralAffairsBudgetRequest;
use App\Models\MusicSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GeneralAffairsController extends Controller
{
    /**
     * Get all budget requests for General Affairs
     */
    public function getBudgetRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $requests = GeneralAffairsBudgetRequest::with(['submission', 'approvedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving budget requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new budget request (triggered by Producer)
     */
    public function createBudgetRequest(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'requested_amount' => 'required|numeric|min:0',
                'purpose' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $budgetRequest = GeneralAffairsBudgetRequest::create([
                'submission_id' => $request->submission_id,
                'requested_amount' => $request->requested_amount,
                'purpose' => $request->purpose,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Budget request created successfully.',
                'data' => $budgetRequest->load(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating budget request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve budget request
     */
    public function approveBudgetRequest($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $budgetRequest = GeneralAffairsBudgetRequest::findOrFail($id);

            if (!$budgetRequest->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Budget request cannot be approved.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $budgetRequest->approve($user->id, $request->approval_notes);

            return response()->json([
                'success' => true,
                'message' => 'Budget request approved successfully.',
                'data' => $budgetRequest->fresh(['submission', 'approvedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving budget request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release funds
     */
    public function releaseFunds($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $budgetRequest = GeneralAffairsBudgetRequest::findOrFail($id);

            if (!$budgetRequest->canBeReleased()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Funds cannot be released.'
                ], 400);
            }

            $budgetRequest->releaseFunds();

            return response()->json([
                'success' => true,
                'message' => 'Funds released successfully.',
                'data' => $budgetRequest->fresh(['submission', 'approvedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error releasing funds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget status
     */
    public function getBudgetStatus($id): JsonResponse
    {
        try {
            $budgetRequest = GeneralAffairsBudgetRequest::with(['submission', 'approvedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $budgetRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving budget status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject budget request
     */
    public function rejectBudgetRequest($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $budgetRequest = GeneralAffairsBudgetRequest::findOrFail($id);

            if (!$budgetRequest->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Budget request cannot be rejected.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $budgetRequest->reject($request->rejection_notes);

            return response()->json([
                'success' => true,
                'message' => 'Budget request rejected.',
                'data' => $budgetRequest->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting budget request: ' . $e->getMessage()
            ], 500);
        }
    }
}
