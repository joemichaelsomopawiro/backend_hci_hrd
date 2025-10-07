<?php

namespace App\Http\Controllers;

use App\Services\WorkflowStateMachine;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WorkflowController extends Controller
{
    protected $workflowStateMachine;

    public function __construct(WorkflowStateMachine $workflowStateMachine)
    {
        $this->workflowStateMachine = $workflowStateMachine;
    }

    /**
     * Get available transitions for entity
     */
    public function getAvailableTransitions(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $entity = $this->getEntity($entityType, $entityId);
            $userRole = Auth::user()->role;
            
            $transitions = $this->workflowStateMachine->getAvailableTransitions(
                $entityType, 
                $entity->status, 
                $userRole
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'current_state' => $entity->status,
                    'available_transitions' => $transitions,
                    'workflow_progress' => $this->workflowStateMachine->getWorkflowStatus($entityType, $entityId)
                ],
                'message' => 'Available transitions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving transitions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute workflow transition
     */
    public function executeTransition(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'transition' => 'required|string',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userRole = Auth::user()->role;
            $success = $this->workflowStateMachine->executeTransition(
                $entityType,
                $entityId,
                $request->transition,
                $userRole,
                $request->data ?? []
            );

            if ($success) {
                $entity = $this->getEntity($entityType, $entityId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'entity' => $entity,
                        'new_state' => $entity->status,
                        'workflow_status' => $this->workflowStateMachine->getWorkflowStatus($entityType, $entityId)
                    ],
                    'message' => 'Transition executed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute transition'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error executing transition: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow status
     */
    public function getWorkflowStatus(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $workflowStatus = $this->workflowStateMachine->getWorkflowStatus($entityType, $entityId);

            return response()->json([
                'success' => true,
                'data' => $workflowStatus,
                'message' => 'Workflow status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow steps for role
     */
    public function getWorkflowSteps(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user ? $user->role : 'guest';
            $workflowSteps = WorkflowStateMachine::WORKFLOW_STEPS[$userRole] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $userRole,
                    'workflow_steps' => $workflowSteps
                ],
                'message' => 'Workflow steps retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow steps: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all workflow states
     */
    public function getWorkflowStates(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'program_states' => WorkflowStateMachine::PROGRAM_STATES,
                    'episode_states' => WorkflowStateMachine::EPISODE_STATES,
                    'schedule_states' => WorkflowStateMachine::SCHEDULE_STATES
                ],
                'message' => 'Workflow states retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow states: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow dashboard
     */
    public function getWorkflowDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $dashboard = [];

            // Get programs with workflow status
            $programs = Program::where('manager_id', $user->id)
                ->orWhere('producer_id', $user->id)
                ->with(['episodes', 'schedules'])
                ->get();

            foreach ($programs as $program) {
                $workflowStatus = $this->workflowStateMachine->getWorkflowStatus('program', $program->id);
                
                $dashboard[] = [
                    'program' => $program,
                    'workflow_status' => $workflowStatus,
                    'episodes_count' => $program->episodes->count(),
                    'schedules_count' => $program->schedules->count(),
                    'overdue_count' => $program->schedules->where('deadline', '<', now())->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Workflow dashboard retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get entity by type and ID
     */
    private function getEntity(string $entityType, int $entityId)
    {
        switch ($entityType) {
            case 'program':
                return Program::findOrFail($entityId);
            case 'episode':
                return Episode::findOrFail($entityId);
            case 'schedule':
                return Schedule::findOrFail($entityId);
            default:
                throw new \Exception("Unsupported entity type: {$entityType}");
        }
    }
}
