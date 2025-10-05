<?php

namespace App\Http\Controllers;

use App\Models\MusicWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class MusicNotificationController extends BaseController
{
    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = MusicWorkflowNotification::where('user_id', $user->id)
                ->with(['submission.song', 'submission.musicArranger'])
                ->orderBy('created_at', 'desc');

            // Filter by read status
            if ($request->has('unread_only') && $request->unread_only) {
                $query->where('is_read', false);
            }

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->where('notification_type', $request->type);
            }

            $perPage = $request->get('per_page', 15);
            $notifications = $query->paginate($perPage);

            return $this->successResponse([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total()
                ]
            ], 'Notifications retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $count = MusicWorkflowNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'unread_count' => $count
            ], 'Unread count retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = MusicWorkflowNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return $this->errorResponse('Notification not found', null, 404);
            }

            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return $this->successResponse([
                'notification' => $notification->fresh()
            ], 'Notification marked as read');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $updated = MusicWorkflowNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return $this->successResponse([
                'updated_count' => $updated
            ], 'All notifications marked as read');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get read status of specific notification
     */
    public function getReadStatus($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = MusicWorkflowNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return $this->errorResponse('Notification not found', null, 404);
            }

            return $this->successResponse([
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at
            ], 'Read status retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Mark notification as read without ID (for frontend compatibility)
     */
    public function markAsReadWithoutId(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notificationId = $request->get('notification_id');
            
            if (!$notificationId) {
                return $this->errorResponse('Notification ID is required', null, 400);
            }

            $notification = MusicWorkflowNotification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return $this->errorResponse('Notification not found', null, 404);
            }

            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return $this->successResponse([
                'notification' => $notification->fresh()
            ], 'Notification marked as read');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}