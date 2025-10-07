<?php

namespace App\Http\Controllers;

use App\Models\ProgramNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $query = ProgramNotification::where('user_id', $user->id);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by read status
            if ($request->has('read')) {
                $query->where('is_read', $request->boolean('read'));
            }

            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::where('user_id', Auth::id())
                ->findOrFail($id);

            $notification->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            ProgramNotification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::where('user_id', Auth::id())
                ->findOrFail($id);

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $statistics = [
                'total_notifications' => ProgramNotification::where('user_id', $user->id)->count(),
                'unread_notifications' => ProgramNotification::where('user_id', $user->id)->where('is_read', false)->count(),
                'notifications_by_type' => ProgramNotification::where('user_id', $user->id)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'recent_notifications' => ProgramNotification::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'type', 'created_at', 'is_read'])
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Notification statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notification statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow notifications
     */
    public function getWorkflowNotifications(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $workflowTypes = [
                'workflow_update',
                'workflow_step_completed',
                'approval_request',
                'deadline_reminder',
                'overdue_alert'
            ];

            $notifications = ProgramNotification::where('user_id', $user->id)
                ->whereIn('type', $workflowTypes)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Workflow notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            $validator = $request->validate([
                'type' => 'required|string',
                'message' => 'required|string',
                'program_id' => 'nullable|exists:programs,id'
            ]);

            $notification = ProgramNotification::create([
                'title' => 'Test Notification',
                'message' => $validator['message'],
                'type' => $validator['type'],
                'user_id' => Auth::id(),
                'program_id' => $validator['program_id']
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Test notification sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending test notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $user->notification_preferences ?? [];

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Notification preferences retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notification preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $validator = $request->validate([
                'email_notifications' => 'boolean',
                'push_notifications' => 'boolean',
                'workflow_notifications' => 'boolean',
                'deadline_reminders' => 'boolean',
                'approval_notifications' => 'boolean',
                'file_upload_notifications' => 'boolean'
            ]);

            $user = Auth::user();
            $currentPreferences = $user->notification_preferences ?? [];
            $updatedPreferences = array_merge($currentPreferences, $validator);
            
            User::where('id', $user->id)->update(['notification_preferences' => $updatedPreferences]);

            return response()->json([
                'success' => true,
                'data' => $updatedPreferences,
                'message' => 'Notification preferences updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating notification preferences: ' . $e->getMessage()
            ], 500);
        }
    }
}
