<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $limit = $request->get('limit', 20);
        $status = $request->get('status');
        
        $notifications = $this->notificationService->getUserNotifications($userId, $limit, $status);
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'message' => 'Notifications retrieved successfully'
        ]);
    }

    /**
     * Get notification by ID
     */
    public function show(int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification retrieved successfully'
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $result = $this->notificationService->markAsRead($id, auth()->id());
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or already read'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead(auth()->id());
            
            return response()->json([
                'success' => true,
                'data' => ['count' => $count],
                'message' => "{$count} notifications marked as read successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as archived
     */
    public function archive(int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        try {
            $notification->markAsArchived();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification archived successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $statistics = $this->notificationService->getNotificationStatistics($userId);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Notification statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $notification = $this->notificationService->sendNotification(
                $request->user_id,
                $request->type,
                $request->title,
                $request->message,
                $request->data ?? [],
                $request->priority ?? 'normal'
            );
            
            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification sent successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $notifications = $this->notificationService->sendNotificationToUsers(
                $request->user_ids,
                $request->type,
                $request->title,
                $request->message,
                $request->data ?? [],
                $request->priority ?? 'normal'
            );
            
            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications sent successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to role
     */
    public function sendToRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $notifications = $this->notificationService->sendNotificationToRole(
                $request->role,
                $request->type,
                $request->title,
                $request->message,
                $request->data ?? [],
                $request->priority ?? 'normal'
            );
            
            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications sent to role successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications to role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notifications
     */
    public function unread(): JsonResponse
    {
        $notifications = $this->notificationService->getUserNotifications(auth()->id(), 50, 'unread');
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'message' => 'Unread notifications retrieved successfully'
        ]);
    }

    /**
     * Get urgent notifications
     */
    public function urgent(): JsonResponse
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->where('priority', 'urgent')
            ->where('status', 'unread')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'message' => 'Urgent notifications retrieved successfully'
        ]);
    }

    /**
     * Clean up old notifications
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $days = $request->get('days', 30);
            $deleted = $this->notificationService->cleanupOldNotifications($days);
            
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => $deleted],
                'message' => "{$deleted} old notifications cleaned up successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup old notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification read status
     * Compatibility endpoint for frontend
     */
    public function getReadStatus(int $id): JsonResponse
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'is_read' => !is_null($notification->read_at),
                    'read_at' => $notification->read_at,
                    'status' => $notification->status
                ],
                'message' => 'Notification read status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}

