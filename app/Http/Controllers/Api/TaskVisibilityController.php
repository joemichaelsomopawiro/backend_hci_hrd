<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskVisibilityController extends Controller
{
    /**
     * Get all tasks with filters
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllTasks(Request $request): JsonResponse
    {
        try {
            // Build filters from request
            $filters = [];

            if ($request->has('status')) {
                $filters['status'] = $request->status;
            }

            if ($request->has('user_id')) {
                $filters['user_id'] = $request->user_id;
            }

            if ($request->has('search')) {
                $filters['search'] = $request->search;
            }

            if ($request->has('date_from')) {
                $filters['date_from'] = $request->date_from;
            }

            if ($request->has('date_to')) {
                $filters['date_to'] = $request->date_to;
            }

            if ($request->has('page')) {
                $filters['page'] = $request->page;
            }

            if ($request->has('per_page')) {
                $filters['per_page'] = min((int)$request->per_page, 100); // Max 100 per page
            }

            $result = TaskVisibilityService::getAllTasks($filters);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta']
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve tasks',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task statistics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTaskStatistics(Request $request): JsonResponse
    {
        try {
            $filters = [];

            if ($request->has('date_from')) {
                $filters['date_from'] = $request->date_from;
            }

            if ($request->has('date_to')) {
                $filters['date_to'] = $request->date_to;
            }

            $stats = TaskVisibilityService::getTaskStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task detail
     * 
     * @param string $taskType
     * @param int $taskId
     * @return JsonResponse
     */
    public function getTaskDetail(string $taskType, int $taskId): JsonResponse
    {
        try {
            $task = TaskVisibilityService::getTaskDetail($taskType, $taskId);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'error' => 'Task not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $task
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve task detail',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
