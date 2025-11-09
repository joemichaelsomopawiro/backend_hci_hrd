<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\ProgramWorkflowService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProgramController extends Controller
{
    protected $programWorkflowService;

    public function __construct(ProgramWorkflowService $programWorkflowService)
    {
        $this->programWorkflowService = $programWorkflowService;
    }

    /**
     * Get all programs
     */
    public function index(Request $request): JsonResponse
    {
        $query = Program::with(['managerProgram', 'productionTeam', 'episodes']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by manager
        if ($request->has('manager_id')) {
            $query->where('manager_program_id', $request->manager_id);
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $programs = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $programs,
            'message' => 'Programs retrieved successfully'
        ]);
    }

    /**
     * Get program by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $program = Program::with([
                'managerProgram',
                'productionTeam.members.user',
                'episodes.deadlines',
                'episodes.workflowStates'
            ])->findOrFail($id);
            
            $analytics = null; // Removed analytics service dependency
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program' => $program,
                    'analytics' => $analytics
                ],
                'message' => 'Program retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new program
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manager_program_id' => 'required|exists:users,id',
            'production_team_id' => 'nullable|exists:production_teams,id',
            'start_date' => 'required|date|after_or_equal:today',
            'air_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'broadcast_channel' => 'nullable|string|max:255',
            'target_views_per_episode' => 'nullable|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Create program dengan data yang sudah divalidasi
            $programData = $request->only([
                'name',
                'description', 
                'manager_program_id',
                'production_team_id',
                'start_date',
                'air_time',
                'duration_minutes',
                'broadcast_channel',
                'target_views_per_episode'
            ]);
            
            // Set default status jika tidak ada
            if (!isset($programData['status'])) {
                $programData['status'] = 'draft';
            }
            
            $program = Program::create($programData);
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update program
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'production_team_id' => 'nullable|exists:production_teams,id',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'air_time' => 'sometimes|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'broadcast_channel' => 'nullable|string|max:255',
            'target_views_per_episode' => 'nullable|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program for approval
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        if ($program->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be submitted from draft status'
            ], 400);
        }
        
        try {
            $program = $this->programWorkflowService->submitProgram($program, auth()->id());
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        if ($program->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be approved from pending_approval status'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = $this->programWorkflowService->approveProgram(
                $program, 
                auth()->id(), 
                $request->approval_notes
            );
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject program
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        if ($program->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be rejected from pending_approval status'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_notes' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = $this->programWorkflowService->rejectProgram(
                $program, 
                auth()->id(), 
                $request->rejection_notes
            );
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program
     */
    public function destroy(int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        if ($program->status === 'in_production') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete program that is in production'
            ], 400);
        }
        
        try {
            $program->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Program deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program analytics
     */
    public function analytics(int $id): JsonResponse
    {
        try {
            $analytics = null; // Removed analytics service dependency
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Program analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get program analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve budget request from Producer
     */
    public function approveBudget(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'budget_amount' => 'required|numeric|min:0',
            'budget_notes' => 'nullable|string|max:1000',
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $program = Program::with('productionTeam')->findOrFail($id);
        
        $program->update([
            'budget_approved' => $request->status === 'approved',
            'budget_amount' => $request->budget_amount,
            'budget_notes' => $request->budget_notes,
            'budget_approved_by' => $user->id,
            'budget_approved_at' => now()
        ]);

        // Notifikasi ke Producer (if production team has producer)
        if ($program->productionTeam && $program->productionTeam->producer_id) {
            \App\Models\Notification::create([
                'user_id' => $program->productionTeam->producer_id,
                'type' => 'budget_approved',
                'title' => 'Budget Disetujui',
                'message' => "Budget program '{$program->name}' telah disetujui: Rp " . number_format($request->budget_amount),
                'data' => [
                    'program_id' => $program->id,
                    'budget_amount' => $request->budget_amount,
                    'status' => $request->status
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Budget berhasil diproses',
            'data' => $program
        ]);
    }

    /**
     * Get budget requests for Manager Program
     */
    public function getBudgetRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $budgetRequests = Program::where('budget_approved', false)
            ->whereNotNull('budget_amount')
            ->with(['managerProgram', 'productionTeam'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $budgetRequests
        ]);
    }

    /**
     * Get program episodes
     */
    public function episodes(int $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        $episodes = $program->episodes()
            ->with(['deadlines', 'workflowStates'])
            ->orderBy('episode_number')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $episodes,
            'message' => 'Program episodes retrieved successfully'
        ]);
    }
}

