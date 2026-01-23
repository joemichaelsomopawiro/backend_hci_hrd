<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskReassignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskReassignmentController extends Controller
{
    /**
     * Reassign task to new user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reassignTask(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check permission
            if (!in_array($user->role, ['Program Manager', 'Producer', 'Manager', 'Manager Program'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Only Program Manager, Producer, or Manager can reassign tasks'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'task_type' => 'required|string',
                'task_id' => 'required|integer',
                'new_user_id' => 'required|integer',
                'reason' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Perform reassignment
            $result = TaskReassignmentService::reassignTask(
                $request->task_type,
                $request->task_id,
                $request->new_user_id,
                $user->id,
                $request->reason
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task successfully reassigned',
                'data' => [
                    'task_id' => $result['data']['task']->id,
                    'reassignment_id' => $result['data']['reassignment']->id,
                    'notifications_sent' => $result['data']['notifications_sent']
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reassign task',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reassignment history for task
     * 
     * @param string $taskType
     * @param int $taskId
     * @return JsonResponse
     */
    public function getReassignmentHistory(string $taskType, int $taskId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check permission (Program Manager and Producer only)
            if (!in_array($user->role, ['Program Manager', 'Producer', 'Manager', 'Manager Program'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Only Program Manager and Producer can view reassignment history'
                ], 403);
            }

            $history = TaskReassignmentService::getReassignmentHistory($taskType, $taskId);

            return response()->json([
                'success' => true,
                'data' => $history
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve reassignment history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available users for task type
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check permission
            if (!in_array($user->role, ['Program Manager', 'Producer', 'Manager', 'Manager Program'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'task_type' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $users = TaskReassignmentService::getAvailableUsers($request->task_type);

            return response()->json([
                'success' => true,
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve available users',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
