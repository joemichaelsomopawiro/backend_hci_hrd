<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Services\DeadlineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DeadlineController extends Controller
{
    protected $deadlineService;

    public function __construct(DeadlineService $deadlineService)
    {
        $this->deadlineService = $deadlineService;
    }

    /**
     * Get all deadlines
     */
    public function index(Request $request): JsonResponse
    {
        $query = Deadline::with(['episode.program', 'completedBy']);
        
        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }
        
        // Filter by program
        if ($request->has('program_id')) {
            $query->whereHas('episode', function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }
        
        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by completion status
        if ($request->has('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }
        
        // Filter by overdue
        if ($request->has('overdue')) {
            if ($request->boolean('overdue')) {
                $query->where('deadline_date', '<', now())
                      ->where('is_completed', false)
                      ->where('status', '!=', 'cancelled');
            }
        }
        
        $deadlines = $query->orderBy('deadline_date')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $deadlines,
            'message' => 'Deadlines retrieved successfully'
        ]);
    }

    /**
     * Get deadline by ID
     */
    public function show(int $id): JsonResponse
    {
        $deadline = Deadline::with(['episode.program', 'completedBy'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $deadline,
            'message' => 'Deadline retrieved successfully'
        ]);
    }

    /**
     * Complete deadline
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $deadline = Deadline::findOrFail($id);
        
        if ($deadline->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Deadline is already completed'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $deadline = $this->deadlineService->completeDeadline(
                $id, 
                auth()->id(), 
                $request->notes
            );
            
            return response()->json([
                'success' => true,
                'data' => $deadline,
                'message' => 'Deadline completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deadline statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $userId = $request->has('user_id') ? $request->user_id : null;
            $statistics = $this->deadlineService->getDeadlineStatistics($userId);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Deadline statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get deadline statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user deadlines
     */
    public function userDeadlines(int $userId): JsonResponse
    {
        $deadlines = $this->deadlineService->getUserDeadlines($userId);
        
        return response()->json([
            'success' => true,
            'data' => $deadlines,
            'message' => 'User deadlines retrieved successfully'
        ]);
    }

    /**
     * Get overdue deadlines
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $userId = $request->has('user_id') ? $request->user_id : null;
            $deadlines = $this->deadlineService->getOverdueDeadlines($userId);
            
            return response()->json([
                'success' => true,
                'data' => $deadlines,
                'message' => 'Overdue deadlines retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get overdue deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming deadlines
     */
    public function upcoming(Request $request): JsonResponse
    {
        try {
            $userId = $request->has('user_id') ? $request->user_id : null;
            $days = $request->get('days', 7);
            $deadlines = $this->deadlineService->getUpcomingDeadlines($userId, $days);
            
            return response()->json([
                'success' => true,
                'data' => $deadlines,
                'message' => 'Upcoming deadlines retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upcoming deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check and update overdue deadlines
     */
    public function checkOverdue(): JsonResponse
    {
        try {
            $updated = $this->deadlineService->checkOverdueDeadlines();
            
            return response()->json([
                'success' => true,
                'data' => $updated,
                'message' => 'Overdue deadlines checked and updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check overdue deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send deadline reminders
     */
    public function sendReminders(): JsonResponse
    {
        try {
            $sent = $this->deadlineService->sendDeadlineReminders();
            
            return response()->json([
                'success' => true,
                'data' => $sent,
                'message' => 'Deadline reminders sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send deadline reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deadline analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $programId = $request->get('program_id');
        $analytics = $this->deadlineService->getDeadlineAnalytics($programId);
        
        return response()->json([
            'success' => true,
            'data' => $analytics,
            'message' => 'Deadline analytics retrieved successfully'
        ]);
    }

    /**
     * Update deadline
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $deadline = Deadline::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'deadline_date' => 'sometimes|date',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $deadline->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $deadline,
                'message' => 'Deadline updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel deadline
     */
    public function cancel(int $id): JsonResponse
    {
        $deadline = Deadline::findOrFail($id);
        
        if ($deadline->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel completed deadline'
            ], 400);
        }
        
        try {
            $deadline->update(['status' => 'cancelled']);
            
            return response()->json([
                'success' => true,
                'data' => $deadline,
                'message' => 'Deadline cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

