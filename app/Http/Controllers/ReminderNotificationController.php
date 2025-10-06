<?php

namespace App\Http\Controllers;

use App\Models\ProgramNotification;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Services\ProgramWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReminderNotificationController extends Controller
{
    protected $workflowService;

    public function __construct(ProgramWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get all notifications for current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = ProgramNotification::where('user_id', $user->id)
                ->with(['program', 'episode', 'schedule']);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by read status
            if ($request->has('is_read')) {
                $query->where('is_read', $request->boolean('is_read'));
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
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
     * Get unread notifications count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = ProgramNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $count],
                'message' => 'Unread count retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unread count: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = ProgramNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification marked as read successfully'
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
            $user = Auth::user();
            $updated = ProgramNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'data' => ['updated_count' => $updated],
                'message' => 'All notifications marked as read successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create custom reminder
     */
    public function createReminder(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'reminder_date' => 'required|date|after:now',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'schedule_id' => 'nullable|exists:schedules,id',
                'reminder_type' => 'required|in:deadline,meeting,production,general',
                'recipients' => 'nullable|array',
                'recipients.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $recipients = $request->recipients ?? [$user->id];

            $notifications = [];
            foreach ($recipients as $recipientId) {
                $notification = ProgramNotification::create([
                    'title' => $request->title,
                    'message' => $request->message,
                    'type' => 'custom_reminder',
                    'user_id' => $recipientId,
                    'program_id' => $request->program_id,
                    'episode_id' => $request->episode_id,
                    'schedule_id' => $request->schedule_id,
                    'scheduled_at' => $request->reminder_date,
                    'data' => [
                        'reminder_type' => $request->reminder_type,
                        'created_by' => $user->id
                    ]
                ]);
                $notifications[] = $notification;
            }

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Reminder created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating reminder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming reminders
     */
    public function getUpcomingReminders(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $days = $request->get('days', 7);
            
            $reminders = ProgramNotification::where('user_id', $user->id)
                ->where('scheduled_at', '>=', now())
                ->where('scheduled_at', '<=', now()->addDays($days))
                ->where('is_read', false)
                ->with(['program', 'episode', 'schedule'])
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reminders,
                'message' => 'Upcoming reminders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving upcoming reminders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deadline reminders
     */
    public function getDeadlineReminders(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $days = $request->get('days', 7);
            
            $deadlineReminders = ProgramNotification::where('user_id', $user->id)
                ->where('type', 'deadline_reminder')
                ->where('scheduled_at', '>=', now())
                ->where('scheduled_at', '<=', now()->addDays($days))
                ->with(['program', 'episode', 'schedule'])
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $deadlineReminders,
                'message' => 'Deadline reminders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving deadline reminders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue alerts
     */
    public function getOverdueAlerts(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $overdueAlerts = ProgramNotification::where('user_id', $user->id)
                ->where('type', 'overdue_alert')
                ->where('is_read', false)
                ->with(['program', 'episode', 'schedule'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $overdueAlerts,
                'message' => 'Overdue alerts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving overdue alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send reminder notifications (cron job endpoint)
     */
    public function sendReminderNotifications(): JsonResponse
    {
        try {
            $this->workflowService->sendReminderNotifications();
            
            return response()->json([
                'success' => true,
                'message' => 'Reminder notifications sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reminder notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update episode statuses (cron job endpoint)
     */
    public function updateEpisodeStatuses(): JsonResponse
    {
        try {
            $this->workflowService->updateEpisodeStatuses();
            
            return response()->json([
                'success' => true,
                'message' => 'Episode statuses updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating episode statuses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-close inactive programs (cron job endpoint)
     */
    public function autoCloseInactivePrograms(): JsonResponse
    {
        try {
            $this->workflowService->autoCloseInactivePrograms();
            
            return response()->json([
                'success' => true,
                'message' => 'Inactive programs auto-closed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error auto-closing inactive programs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set automatic deadlines for program (cron job endpoint)
     */
    public function setAutomaticDeadlines(string $programId): JsonResponse
    {
        try {
            $program = Program::findOrFail($programId);
            $this->workflowService->setAutomaticDeadlines($program);
            
            return response()->json([
                'success' => true,
                'message' => 'Automatic deadlines set successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error setting automatic deadlines: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification preferences
     */
    public function getNotificationPreferences(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Default preferences
            $preferences = [
                'email_notifications' => true,
                'push_notifications' => true,
                'deadline_reminders' => true,
                'overdue_alerts' => true,
                'approval_notifications' => true,
                'workflow_updates' => true,
                'reminder_frequency' => 'daily', // daily, weekly, never
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '08:00'
            ];

            // Load from user preferences if available
            if ($user->notification_preferences) {
                $userPreferences = is_string($user->notification_preferences) ? 
                    json_decode($user->notification_preferences, true) : $user->notification_preferences;
                $preferences = array_merge($preferences, $userPreferences);
            }

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
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_notifications' => 'boolean',
                'push_notifications' => 'boolean',
                'deadline_reminders' => 'boolean',
                'overdue_alerts' => 'boolean',
                'approval_notifications' => 'boolean',
                'workflow_updates' => 'boolean',
                'reminder_frequency' => 'in:daily,weekly,never',
                'quiet_hours_start' => 'date_format:H:i',
                'quiet_hours_end' => 'date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $preferences = $request->all();
            
            $user->update([
                'notification_preferences' => $preferences
            ]);

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Notification preferences updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating notification preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = ProgramNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

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
     * Bulk delete notifications
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notification_ids' => 'required|array',
                'notification_ids.*' => 'exists:program_notifications,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $deletedCount = ProgramNotification::where('user_id', $user->id)
                ->whereIn('id', $request->notification_ids)
                ->delete();

            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => $deletedCount],
                'message' => 'Notifications deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting notifications: ' . $e->getMessage()
            ], 500);
        }
    }
}
