<?php

namespace App\Http\Controllers;

use App\Models\BudgetApproval;
use App\Services\CreativeWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ManagerProgramController extends Controller
{
    protected $workflowService;

    public function __construct(CreativeWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get all budget approvals (with filters)
     * GET /api/music/manager-program/budget-approvals
     */
    public function getBudgetApprovals(Request $request)
    {
        try {
            $query = BudgetApproval::with([
                'budget',
                'musicSubmission.song',
                'musicSubmission.musicArranger',
                'requester',
            ])->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter pending only
            if ($request->boolean('pending_only')) {
                $query->where('status', 'pending');
            }

            $approvals = $query->get();

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'count' => $approvals->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get budget approvals: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget approval detail
     * GET /api/music/manager-program/budget-approvals/{id}
     */
    public function getBudgetApprovalDetail($id)
    {
        try {
            $approval = BudgetApproval::with([
                'budget',
                'musicSubmission' => function ($query) {
                    $query->with([
                        'song',
                        'musicArranger',
                        'creativeWork',
                        'schedules'
                    ]);
                },
                'requester',
                'approver',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $approval,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Budget approval not found',
            ], 404);
        }
    }

    /**
     * Approve budget (full amount or revised amount)
     * POST /api/music/manager-program/budget-approvals/{id}/approve
     */
    public function approveBudget(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:approved',
            'approved_amount' => 'nullable|numeric|min:0',
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->workflowService->processSpecialBudgetApproval($id, $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Reject budget
     * POST /api/music/manager-program/budget-approvals/{id}/reject
     */
    public function rejectBudget(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:rejected',
            'approval_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->workflowService->processSpecialBudgetApproval($id, $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Get dashboard stats for Manager Program
     * GET /api/music/manager-program/dashboard
     */
    public function getDashboard()
    {
        try {
            $pendingApprovals = BudgetApproval::where('status', 'pending')->count();
            $approvedThisMonth = BudgetApproval::where('status', 'approved')
                ->whereMonth('approved_at', now()->month)
                ->count();
            $rejectedThisMonth = BudgetApproval::where('status', 'rejected')
                ->whereMonth('rejected_at', now()->month)
                ->count();
            
            $totalBudgetApproved = BudgetApproval::where('status', 'approved')
                ->whereMonth('approved_at', now()->month)
                ->sum('approved_amount');

            $recentApprovals = BudgetApproval::with([
                'musicSubmission.song',
                'requester'
            ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'pending_approvals' => $pendingApprovals,
                        'approved_this_month' => $approvedThisMonth,
                        'rejected_this_month' => $rejectedThisMonth,
                        'total_budget_approved_this_month' => $totalBudgetApproved,
                    ],
                    'recent_approvals' => $recentApprovals,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard data: ' . $e->getMessage(),
            ], 500);
        }
    }
}






