<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowStateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkflowStateController extends Controller
{
    protected $workflowStateService;

    public function __construct(WorkflowStateService $workflowStateService)
    {
        $this->workflowStateService = $workflowStateService;
    }

    /**
     * Get workflow state transitions
     */
    public function getTransitions(): JsonResponse
    {
        try {
            $transitions = $this->workflowStateService->getWorkflowStateTransitions();
            
            return response()->json([
                'success' => true,
                'data' => $transitions,
                'message' => 'Workflow state transitions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow state transitions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow state labels
     */
    public function getLabels(): JsonResponse
    {
        try {
            $stateLabels = $this->workflowStateService->getWorkflowStateLabels();
            $roleLabels = $this->workflowStateService->getRoleLabels();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'state_labels' => $stateLabels,
                    'role_labels' => $roleLabels
                ],
                'message' => 'Workflow state labels retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow state labels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes by workflow state
     */
    public function getEpisodesByState(string $state): JsonResponse
    {
        try {
            $episodes = $this->workflowStateService->getEpisodesByWorkflowState($state);
            
            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes by workflow state retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get episodes by workflow state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user workflow tasks
     */
    public function getUserTasks(int $userId): JsonResponse
    {
        try {
            $tasks = $this->workflowStateService->getUserWorkflowTasks($userId);
            
            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'User workflow tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user workflow tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $programId = $request->get('program_id');
            $analytics = $this->workflowStateService->getWorkflowStatistics($programId);
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Workflow analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if workflow state transition is valid
     */
    public function checkTransition(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'current_state' => 'required|string',
            'new_state' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $isValid = $this->workflowStateService->isValidTransition(
                $request->current_state,
                $request->new_state
            );
            
            $nextStates = $this->workflowStateService->getNextPossibleStates($request->current_state);
            $previousStates = $this->workflowStateService->getPreviousPossibleStates($request->new_state);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'next_possible_states' => $nextStates,
                    'previous_possible_states' => $previousStates
                ],
                'message' => 'Workflow state transition check completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check workflow state transition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next possible states
     */
    public function getNextStates(string $currentState): JsonResponse
    {
        try {
            $nextStates = $this->workflowStateService->getNextPossibleStates($currentState);
            
            return response()->json([
                'success' => true,
                'data' => $nextStates,
                'message' => 'Next possible states retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get next possible states',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get previous possible states
     */
    public function getPreviousStates(string $currentState): JsonResponse
    {
        try {
            $previousStates = $this->workflowStateService->getPreviousPossibleStates($currentState);
            
            return response()->json([
                'success' => true,
                'data' => $previousStates,
                'message' => 'Previous possible states retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get previous possible states',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














