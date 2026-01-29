<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PrWorkflowService;
use App\Constants\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Services\PrActivityLogService;
use App\Models\PrEpisode;

class PrEpisodeWorkflowController extends Controller
{
    protected $workflowService;
    protected $activityLogService;

    public function __construct(PrWorkflowService $workflowService, PrActivityLogService $activityLogService)
    {
        $this->workflowService = $workflowService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Get workflow visualization data untuk episode
     * Semua role bisa access untuk monitoring
     * 
     * GET /api/program-regular/episodes/{episode}/workflow
     */
    public function getWorkflow(int $episodeId): JsonResponse
    {
        try {
            $data = $this->workflowService->getWorkflowVisualization($episodeId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start a workflow step
     * User harus punya role yang sesuai dengan step
     * 
     * POST /api/program-regular/episodes/{episode}/workflow/start-step
     * Body: { step_number: int }
     */
    public function startStep(Request $request, int $episodeId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'step_number' => 'required|integer|min:1|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $stepNumber = $request->step_number;

            // Check if user role can access this step
            if (!$this->workflowService->canUserAccessStep($user, $stepNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Role Anda tidak bisa memulai step ini',
                    'user_role' => $user->role,
                    'required_roles' => WorkflowStep::getRolesForStep($stepNumber)
                ], 403);
            }

            $progress = $this->workflowService->startStep($episodeId, $stepNumber, $user->id);

            // Log Activity
            $episode = PrEpisode::find($episodeId);
            if ($episode) {
                $this->activityLogService->logEpisodeActivity(
                    $episode,
                    'start_step',
                    "Started workflow step $stepNumber",
                    ['step' => $stepNumber]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Workflow step berhasil dimulai',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete a workflow step
     * User harus punya role yang sesuai dengan step
     * 
     * POST /api/program-regular/episodes/{episode}/workflow/complete-step
     * Body: { step_number: int, notes?: string }
     */
    public function completeStep(Request $request, int $episodeId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'step_number' => 'required|integer|min:1|max:10',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $stepNumber = $request->step_number;

            // Check if user role can access this step
            if (!$this->workflowService->canUserAccessStep($user, $stepNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Role Anda tidak bisa complete step ini',
                    'user_role' => $user->role,
                    'required_roles' => WorkflowStep::getRolesForStep($stepNumber)
                ], 403);
            }

            $progress = $this->workflowService->completeStep($episodeId, $stepNumber, $request->notes);

            // Log Activity
            $episode = PrEpisode::find($episodeId);
            if ($episode) {
                $this->activityLogService->logEpisodeActivity(
                    $episode,
                    'complete_step',
                    "Completed workflow step $stepNumber",
                    ['step' => $stepNumber, 'notes' => $request->notes]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Workflow step berhasil diselesaikan',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow history
     * 
     * GET /api/program-regular/episodes/{episode}/workflow/history
     */
    public function getHistory(int $episodeId): JsonResponse
    {
        try {
            $history = $this->workflowService->getWorkflowHistory($episodeId);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workflow history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign user to a workflow step
     * Hanya Manager Program yang bisa assign
     * 
     * POST /api/program-regular/episodes/{episode}/workflow/assign
     * Body: { step_number: int, user_id: int }
     */
    public function assignUser(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only Manager Program can assign users
            if (\App\Constants\Role::normalize($user->role) !== \App\Constants\Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Manager Program can assign users'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'step_number' => 'required|integer|min:1|max:10',
                'user_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $progress = $this->workflowService->assignUser(
                $episodeId,
                $request->step_number,
                $request->user_id
            );

            // Log Activity
            $episode = PrEpisode::find($episodeId);
            $assignedUser = \App\Models\User::find($request->user_id);
            if ($episode && $assignedUser) {
                $this->activityLogService->logEpisodeActivity(
                    $episode,
                    'assign_step_user',
                    "Assigned user {$assignedUser->name} to step {$request->step_number}",
                    ['step' => $request->step_number, 'assigned_user_id' => $request->user_id]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'User berhasil di-assign ke workflow step',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update step notes
     * User dengan role yang sesuai bisa update notes
     * 
     * PUT /api/program-regular/episodes/{episode}/workflow/notes
     * Body: { step_number: int, notes: string }
     */
    public function updateNotes(Request $request, int $episodeId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'step_number' => 'required|integer|min:1|max:10',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $stepNumber = $request->step_number;

            // Check if user role can access this step
            if (!$this->workflowService->canUserAccessStep($user, $stepNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Role Anda tidak bisa update notes step ini'
                ], 403);
            }

            $progress = $this->workflowService->updateStepNotes($episodeId, $stepNumber, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Notes berhasil diupdate',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset a workflow step (Manager Program only)
     * 
     * POST /api/program-regular/episodes/{episode}/workflow/reset-step
     * Body: { step_number: int }
     */
    public function resetStep(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only Manager Program can reset steps
            if (\App\Constants\Role::normalize($user->role) !== \App\Constants\Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Manager Program can reset workflow steps'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'step_number' => 'required|integer|min:1|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $progress = $this->workflowService->resetStep($episodeId, $request->step_number);

            // Log Activity
            $episode = PrEpisode::find($episodeId);
            if ($episode) {
                $this->activityLogService->logEpisodeActivity(
                    $episode,
                    'reset_step',
                    "Reset workflow step {$request->step_number}",
                    ['step' => $request->step_number]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Workflow step berhasil direset',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
