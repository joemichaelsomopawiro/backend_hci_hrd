<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Episode;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Send notification to user
     */
    public function sendNotification(int $userId, string $type, string $title, string $message, array $data = [], string $priority = 'normal'): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'status' => 'unread'
        ]);
    }

    /**
     * Send notification to multiple users
     */
    public function sendNotificationToUsers(array $userIds, string $type, string $title, string $message, array $data = [], string $priority = 'normal'): array
    {
        $notifications = [];
        
        foreach ($userIds as $userId) {
            $notifications[] = $this->sendNotification($userId, $type, $title, $message, $data, $priority);
        }
        
        return $notifications;
    }

    /**
     * Send notification to role
     */
    public function sendNotificationToRole(string $role, string $type, string $title, string $message, array $data = [], string $priority = 'normal'): array
    {
        $users = User::where('role', $role)->pluck('id')->toArray();
        return $this->sendNotificationToUsers($users, $type, $title, $message, $data, $priority);
    }

    /**
     * Send deadline reminder
     */
    public function sendDeadlineReminder(Episode $episode, string $role, string $deadlineDate): void
    {
        $deadline = $episode->deadlines()->where('role', $role)->first();
        if (!$deadline) return;

        $user = $episode->program->productionTeam->members()
            ->where('role', $role)
            ->where('is_active', true)
            ->first()?->user;

        if ($user) {
            $this->sendNotification(
                $user->id,
                'deadline_reminder',
                'Deadline Reminder',
                "You have a deadline for Episode {$episode->episode_number}: {$episode->title} on {$deadlineDate}",
                [
                    'episode_id' => $episode->id,
                    'deadline_id' => $deadline->id,
                    'deadline_date' => $deadlineDate
                ],
                'high'
            );
        }
    }

    /**
     * Send overdue deadline notification
     */
    public function sendOverdueDeadlineNotification(Episode $episode, string $role): void
    {
        $deadline = $episode->deadlines()->where('role', $role)->first();
        if (!$deadline || $deadline->is_completed) return;

        $user = $episode->program->productionTeam->members()
            ->where('role', $role)
            ->where('is_active', true)
            ->first()?->user;

        if ($user) {
            $this->sendNotification(
                $user->id,
                'deadline_overdue',
                'Deadline Overdue',
                "Your deadline for Episode {$episode->episode_number}: {$episode->title} is overdue!",
                [
                    'episode_id' => $episode->id,
                    'deadline_id' => $deadline->id,
                    'deadline_date' => $deadline->deadline_date
                ],
                'urgent'
            );
        }
    }

    /**
     * Send workflow state change notification
     */
    public function sendWorkflowStateChangeNotification(Episode $episode, string $newState, string $assignedRole, ?int $assignedUserId = null): void
    {
        if ($assignedUserId) {
            $this->sendNotification(
                $assignedUserId,
                'workflow_state_change',
                'New Task Assigned',
                "You have been assigned to work on Episode {$episode->episode_number}: {$episode->title}",
                [
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'new_state' => $newState,
                    'assigned_role' => $assignedRole
                ],
                'normal'
            );
        }
    }

    /**
     * Send budget request notification
     */
    public function sendBudgetRequestNotification(Episode $episode, float $amount, string $budgetType): void
    {
        $managerProgram = $episode->program->managerProgram;
        
        $this->sendNotification(
            $managerProgram->id,
            'budget_request',
            'Budget Request',
            "Budget request for Episode {$episode->episode_number}: {$episode->title} - {$budgetType} (Rp " . number_format($amount, 0, ',', '.') . ")",
            [
                'episode_id' => $episode->id,
                'program_id' => $episode->program_id,
                'amount' => $amount,
                'budget_type' => $budgetType
            ],
            'high'
        );
    }

    /**
     * Send equipment request notification
     */
    public function sendEquipmentRequestNotification(Episode $episode, array $equipmentList): void
    {
        $artSetDesignUsers = User::where('role', 'art_set_design')->get();
        
        foreach ($artSetDesignUsers as $user) {
            $this->sendNotification(
                $user->id,
                'equipment_request',
                'Equipment Request',
                "Equipment request for Episode {$episode->episode_number}: {$episode->title}",
                [
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'equipment_list' => $equipmentList
                ],
                'normal'
            );
        }
    }

    /**
     * Send quality control notification
     */
    public function sendQualityControlNotification(Episode $episode, string $qcType): void
    {
        $qcUsers = User::where('role', 'quality_control')->get();
        
        foreach ($qcUsers as $user) {
            $this->sendNotification(
                $user->id,
                'quality_control',
                'Quality Control Required',
                "QC required for Episode {$episode->episode_number}: {$episode->title} - {$qcType}",
                [
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'qc_type' => $qcType
                ],
                'normal'
            );
        }
    }

    /**
     * Send broadcasting notification
     */
    public function sendBroadcastingNotification(Episode $episode, string $platform): void
    {
        $broadcastingUsers = User::where('role', 'broadcasting')->get();
        
        foreach ($broadcastingUsers as $user) {
            $this->sendNotification(
                $user->id,
                'broadcasting',
                'Broadcasting Required',
                "Broadcasting required for Episode {$episode->episode_number}: {$episode->title} - {$platform}",
                [
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'platform' => $platform
                ],
                'normal'
            );
        }
    }

    /**
     * Send promotion notification
     */
    public function sendPromotionNotification(Episode $episode, string $materialType): void
    {
        $promotionUsers = User::where('role', 'promotion')->get();
        
        foreach ($promotionUsers as $user) {
            $this->sendNotification(
                $user->id,
                'promotion',
                'Promotion Required',
                "Promotion required for Episode {$episode->episode_number}: {$episode->title} - {$materialType}",
                [
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'material_type' => $materialType
                ],
                'normal'
            );
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->first();
                
            if (!$notification) {
                return false;
            }
            
            $notification->markAsRead();
            return true;
        } catch (\Exception $e) {
            \Log::error('Error marking notification as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $limit = 20, ?string $status = null): array
    {
        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');
            
        if ($status) {
            $query->where('status', $status);
        }
        
        $notifications = $query->limit($limit)->get();
        
        return $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => $notification->priority,
                'priority_label' => $notification->priority_label,
                'priority_color' => $notification->priority_color,
                'status' => $notification->status,
                'is_read' => $notification->isRead(),
                'is_archived' => $notification->isArchived(),
                'created_at' => $notification->created_at,
                'read_at' => $notification->read_at,
                'data' => $notification->data
            ];
        })->toArray();
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(int $userId): array
    {
        $total = Notification::where('user_id', $userId)->count();
        $unread = Notification::where('user_id', $userId)->where('status', 'unread')->count();
        $urgent = Notification::where('user_id', $userId)->where('priority', 'urgent')->where('status', 'unread')->count();
        $high = Notification::where('user_id', $userId)->where('priority', 'high')->where('status', 'unread')->count();
        
        return [
            'total' => $total,
            'unread' => $unread,
            'urgent' => $urgent,
            'high' => $high,
            'read' => $total - $unread
        ];
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $days = 30): int
    {
        $cutoffDate = now()->subDays($days);
        
        return Notification::where('created_at', '<', $cutoffDate)
            ->where('status', 'read')
            ->delete();
    }
}