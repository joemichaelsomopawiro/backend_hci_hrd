<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ProgramNotification;
use App\Models\MusicNotification;
use App\Models\MusicWorkflowNotification;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified Notification Service
 * 
 * Service untuk mengkonsolidasikan semua notifikasi dari berbagai sumber
 * ke dalam satu endpoint yang sama berdasarkan role user
 */
class UnifiedNotificationService
{
    /**
     * Get all notifications for user based on their role
     */
    public function getAllNotificationsForUser(int $userId, array $filters = []): array
    {
        try {
            $user = User::findOrFail($userId);
            $notifications = [];

            // 1. Get notifications from main Notification model
            try {
                $mainNotifications = $this->getMainNotifications($userId, $filters);
                $notifications = array_merge($notifications, $mainNotifications);
            } catch (\Throwable $e) {
                Log::warning('Error getting main notifications: ' . $e->getMessage());
            }

            // 2. Get Program Notifications
            try {
                $programNotifications = $this->getProgramNotifications($userId, $filters);
                $notifications = array_merge($notifications, $programNotifications);
            } catch (\Throwable $e) {
                Log::warning('Error getting program notifications: ' . $e->getMessage());
            }

            // 3. Get Music Notifications
            try {
                $musicNotifications = $this->getMusicNotifications($userId, $filters);
                $notifications = array_merge($notifications, $musicNotifications);
            } catch (\Throwable $e) {
                Log::warning('Error getting music notifications: ' . $e->getMessage());
            }

            // 4. Get Music Workflow Notifications
            try {
                $musicWorkflowNotifications = $this->getMusicWorkflowNotifications($userId, $filters);
                $notifications = array_merge($notifications, $musicWorkflowNotifications);
            } catch (\Throwable $e) {
                Log::warning('Error getting music workflow notifications: ' . $e->getMessage());
            }

            // 5. Get Leave Request Notifications (based on role)
            try {
                $leaveNotifications = $this->getLeaveRequestNotifications($user, $filters);
                $notifications = array_merge($notifications, $leaveNotifications);
            } catch (\Exception $e) {
                Log::warning('Error getting leave request notifications: ' . $e->getMessage());
            }

            // Sort by created_at descending
            usort($notifications, function ($a, $b) {
                try {
                    return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
                } catch (\Exception $e) {
                    return 0;
                }
            });

            return $notifications;
        } catch (\Exception $e) {
            Log::error('Error in getAllNotificationsForUser: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get notifications from main Notification model
     */
    private function getMainNotifications(int $userId, array $filters): array
    {
        $query = Notification::where('user_id', $userId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return $notifications->map(function ($notification) {
            return [
                'id' => 'main_' . $notification->id,
                'source' => 'main',
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => $notification->priority ?? 'normal',
                'status' => $notification->status,
                'is_read' => $notification->isRead(),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->toDateTimeString(),
                'data' => $notification->data ?? [],
                'episode_id' => $notification->episode_id,
                'program_id' => $notification->program_id,
            ];
        })->toArray();
    }

    /**
     * Get Program Notifications
     */
    private function getProgramNotifications(int $userId, array $filters): array
    {
        $query = ProgramNotification::where('user_id', $userId);

        if (isset($filters['status'])) {
            $isRead = $filters['status'] === 'read';
            $query->where('is_read', $isRead);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return $notifications->map(function ($notification) {
            return [
                'id' => 'program_' . $notification->id,
                'source' => 'program',
                'type' => $notification->type ?? 'program_notification',
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => 'normal',
                'status' => $notification->is_read ? 'read' : 'unread',
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toDateTimeString(),
                'created_at' => $notification->created_at->toDateTimeString(),
                'data' => $notification->data ?? [],
                'episode_id' => $notification->episode_id,
                'program_id' => $notification->program_id,
            ];
        })->toArray();
    }

    /**
     * Get Music Notifications
     */
    private function getMusicNotifications(int $userId, array $filters): array
    {
        $query = MusicNotification::where('user_id', $userId);

        if (isset($filters['status'])) {
            $isRead = $filters['status'] === 'read';
            $query->where('is_read', $isRead);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return $notifications->map(function ($notification) {
            return [
                'id' => 'music_' . $notification->id,
                'source' => 'music',
                'type' => $notification->type ?? 'music_notification',
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => 'normal',
                'status' => $notification->is_read ? 'read' : 'unread',
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toDateTimeString(),
                'created_at' => $notification->created_at->toDateTimeString(),
                'data' => [
                    'music_request_id' => $notification->music_request_id ?? null,
                ],
            ];
        })->toArray();
    }

    /**
     * Get Music Workflow Notifications
     */
    private function getMusicWorkflowNotifications(int $userId, array $filters): array
    {
        $query = MusicWorkflowNotification::where('user_id', $userId);

        if (isset($filters['status'])) {
            $isRead = $filters['status'] === 'read';
            $query->where('is_read', $isRead);
        }

        if (isset($filters['type'])) {
            $query->where('notification_type', $filters['type']);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return $notifications->map(function ($notification) {
            return [
                'id' => 'musicworkflow_' . $notification->id,
                'source' => 'music_workflow',
                'type' => $notification->notification_type ?? 'music_workflow',
                'title' => $notification->title,
                'message' => $notification->message,
                'priority' => 'normal',
                'status' => $notification->is_read ? 'read' : 'unread',
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toDateTimeString(),
                'created_at' => $notification->created_at->toDateTimeString(),
                'data' => [
                    'submission_id' => $notification->submission_id ?? null,
                ],
            ];
        })->toArray();
    }

    /**
     * Get Leave Request Notifications based on user role
     */
    private function getLeaveRequestNotifications(User $user, array $filters): array
    {
        $notifications = [];

        try {
            $userRole = $user->role;

            // Check if user can approve leave requests
            try {
                $canApprove = \App\Services\RoleHierarchyService::canApproveLeave($userRole, null);
            } catch (\Exception $e) {
                Log::warning('Error checking canApproveLeave: ' . $e->getMessage());
                $canApprove = false;
            }

            if ($canApprove) {
                try {
                    // Get subordinate roles
                    $subordinateRoles = \App\Services\RoleHierarchyService::getSubordinateRoles($userRole);

                    if (empty($subordinateRoles)) {
                        return $notifications;
                    }

                    // Get pending leave requests that need approval
                    $pendingRequests = LeaveRequest::where('overall_status', 'pending')
                        ->whereHas('employee.user', function ($query) use ($subordinateRoles) {
                            $query->whereIn('role', $subordinateRoles);
                        })
                        ->with(['employee.user'])
                        ->orderBy('created_at', 'desc')
                        ->limit($filters['limit'] ?? 50)
                        ->get();
                } catch (\Exception $e) {
                    Log::warning('Error getting pending leave requests: ' . $e->getMessage());
                    $pendingRequests = collect([]);
                }

                foreach ($pendingRequests as $request) {
                    try {
                        $employee = $request->employee;
                        if (!$employee)
                            continue;

                        $employeeUser = $employee->user;
                        if (!$employeeUser)
                            continue;

                        $notifications[] = [
                            'id' => 'leave_' . $request->id,
                            'source' => 'leave_request',
                            'type' => 'leave_approval_required',
                            'title' => 'Permohonan Cuti Menunggu Persetujuan',
                            'message' => "{$employeeUser->name} mengajukan permohonan cuti dari {$request->start_date} sampai {$request->end_date}",
                            'priority' => 'high',
                            'status' => 'unread',
                            'is_read' => false,
                            'read_at' => null,
                            'created_at' => $request->created_at->toDateTimeString(),
                            'data' => [
                                'leave_request_id' => $request->id,
                                'employee_id' => $employee->id,
                                'employee_name' => $employeeUser->name,
                                'start_date' => $request->start_date,
                                'end_date' => $request->end_date,
                                'leave_type' => $request->leave_type,
                                'days' => $request->days,
                            ],
                        ];
                    } catch (\Exception $e) {
                        Log::warning('Error processing leave request notification: ' . $e->getMessage(), [
                            'request_id' => $request->id ?? null
                        ]);
                    }
                }
            }

            // Get leave requests for the user's own employee record
            if ($user->employee_id) {
                try {
                    $myLeaveRequests = LeaveRequest::where('employee_id', $user->employee_id)
                        ->whereIn('overall_status', ['approved', 'rejected'])
                        ->orderBy('updated_at', 'desc')
                        ->limit($filters['limit'] ?? 20)
                        ->get();

                    foreach ($myLeaveRequests as $request) {
                        try {
                            $status = $request->overall_status;
                            $notifications[] = [
                                'id' => 'leave_' . $request->id,
                                'source' => 'leave_request',
                                'type' => $status === 'approved' ? 'leave_approved' : 'leave_rejected',
                                'title' => $status === 'approved'
                                    ? 'Permohonan Cuti Disetujui'
                                    : 'Permohonan Cuti Ditolak',
                                'message' => $status === 'approved'
                                    ? "Permohonan cuti Anda dari {$request->start_date} sampai {$request->end_date} telah disetujui"
                                    : "Permohonan cuti Anda dari {$request->start_date} sampai {$request->end_date} telah ditolak",
                                'priority' => $status === 'approved' ? 'normal' : 'high',
                                'status' => 'unread',
                                'is_read' => false,
                                'read_at' => null,
                                'created_at' => $request->updated_at->toDateTimeString(),
                                'data' => [
                                    'leave_request_id' => $request->id,
                                    'start_date' => $request->start_date,
                                    'end_date' => $request->end_date,
                                    'leave_type' => $request->leave_type,
                                    'days' => $request->days,
                                    'status' => $status,
                                ],
                            ];
                        } catch (\Exception $e) {
                            Log::warning('Error processing my leave request notification: ' . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error getting my leave requests: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in getLeaveRequestNotifications: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $notifications;
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $notifications = $this->getAllNotificationsForUser($userId, ['status' => 'unread']);
            return count($notifications);
        } catch (\Exception $e) {
            Log::error('Error getting unread count: ' . $e->getMessage(), [
                'user_id' => $userId
            ]);
            return 0;
        }
    }

    /**
     * Mark notification as read (handles different sources)
     */
    public function markAsRead(string $notificationId, int $userId): bool
    {
        try {
            // Check if it's a leave request notification
            if (strpos($notificationId, 'leave_') === 0) {
                // For leave requests, we don't mark as read in database
                // They will be marked as read when user views the leave request
                return true;
            }

            // Extract source and ID
            // Format: {source}_{id} (e.g., "main_123", "program_456", "music_789")
            $parts = explode('_', $notificationId, 2);
            if (count($parts) === 2) {
                $source = $parts[0];
                $id = $parts[1];
            } else {
                // Try to find in main notifications (backward compatibility)
                $notification = Notification::where('id', $notificationId)
                    ->where('user_id', $userId)
                    ->first();

                if ($notification) {
                    $notification->markAsRead();
                    return true;
                }
                return false;
            }

            switch ($source) {
                case 'main':
                    $notification = Notification::where('id', $id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($notification) {
                        $notification->markAsRead();
                        return true;
                    }
                    break;

                case 'program':
                    $notification = ProgramNotification::where('id', $id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($notification) {
                        $notification->markAsRead();
                        return true;
                    }
                    break;

                case 'music':
                    $notification = MusicNotification::where('id', $id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($notification) {
                        $notification->markAsRead();
                        return true;
                    }
                    break;

                case 'musicworkflow':
                    // ID format: musicworkflow_{id}
                    $notification = MusicWorkflowNotification::where('id', $id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($notification) {
                        $notification->markAsRead();
                        return true;
                    }
                    break;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage(), [
                'notification_id' => $notificationId,
                'user_id' => $userId
            ]);
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        $count = 0;

        // Mark main notifications
        $count += Notification::where('user_id', $userId)
            ->where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);

        // Mark program notifications
        $count += ProgramNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        // Mark music notifications
        $count += MusicNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        // Mark music workflow notifications
        $count += MusicWorkflowNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $count;
    }
}

