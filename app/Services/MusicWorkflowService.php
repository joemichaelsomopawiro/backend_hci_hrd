<?php

namespace App\Services;

use App\Models\MusicSubmission;
use App\Models\MusicWorkflowHistory;
use App\Models\MusicWorkflowNotification;
use App\Models\User;
use App\Events\MusicWorkflowNotificationSent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Music Workflow Service
 * 
 * Handles all music workflow operations including submissions,
 * state transitions, notifications, and analytics.
 */
class MusicWorkflowService
{
    /**
     * Get current active submission for user
     */
    public function getCurrentSubmission(int $userId): ?MusicSubmission
    {
        return MusicSubmission::where('music_arranger_id', $userId)
            ->whereIn('current_state', ['submitted', 'producer_review', 'arranging', 'arrangement_review'])
            ->with(['song', 'proposedSinger', 'approvedSinger', 'musicArranger'])
            ->first();
    }

    /**
     * Get workflow list with filters
     */
    public function getWorkflowList(array $filters = []): array
    {
        $query = MusicSubmission::with(['song', 'proposedSinger', 'approvedSinger', 'musicArranger']);

        if (isset($filters['status']) && $filters['status']) {
            $query->where('current_state', $filters['status']);
        }

        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('music_arranger_id', $filters['user_id']);
        }

        if (isset($filters['urgent']) && $filters['urgent']) {
            $query->where('is_urgent', true);
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $result = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'data' => $result->items(),
            'total_pages' => $result->lastPage(),
            'current_page' => $result->currentPage(),
            'total' => $result->total()
        ];
    }

    /**
     * Create new submission
     */
    public function createSubmission(array $data): MusicSubmission
    {
        Log::info('MusicWorkflowService::createSubmission called with data:', $data);
        
        return DB::transaction(function () use ($data) {
            Log::info('Creating MusicSubmission with data:', $data);
            $submission = MusicSubmission::create($data);
            Log::info('MusicSubmission created with ID:', ['id' => $submission->id]);

            // Load song relationship for notification
            Log::info('Loading song relationship...');
            $submission->load('song');
            Log::info('Song loaded:', ['title' => $submission->song->title ?? 'No title']);
            
            // Create notification for producer (simplified)
            Log::info('Creating notification...');
            try {
                $this->createNotification(
                    $submission->id,
                    'Producer',
                    'submission_received',
                    'New Music Submission',
                    "New submission for song: {$submission->song->title}"
                );
                Log::info('Notification created successfully');
            } catch (Exception $e) {
                Log::warning('Failed to create notification: ' . $e->getMessage());
                // Continue without notification
            }

            return $submission;
        });
    }

    /**
     * Transition workflow state
     */
    public function transitionState(
        int $submissionId,
        string $newState,
        int $userId,
        ?string $notes = null,
        ?int $assignedUserId = null
    ): void {
        DB::transaction(function () use ($submissionId, $newState, $userId, $notes, $assignedUserId) {
            $submission = MusicSubmission::findOrFail($submissionId);
            $oldState = $submission->current_state;

            // Validate transition
            if (!$submission->canTransitionTo($newState)) {
                throw new Exception("Invalid transition from {$oldState} to {$newState}");
            }

            // Update submission state
            $submission->update(['current_state' => $newState]);

            // Create workflow history entry
            $submission->workflowHistory()->create([
                'from_state' => $oldState,
                'to_state' => $newState,
                'action_by_user_id' => $userId,
                'action_notes' => $notes
            ]);

            // Create state-specific notifications
            $this->createStateNotifications($submission, $newState, $oldState);
        });
    }

    /**
     * Get workflow history
     */
    public function getWorkflowHistory(int $submissionId): Collection
    {
        return MusicWorkflowHistory::where('submission_id', $submissionId)
            ->with(['actionByUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get notifications for user
     */
    public function getNotifications(int $userId, array $filters = []): array
    {
        $query = MusicWorkflowNotification::where('user_id', $userId)
            ->with(['submission.song', 'submission.musicArranger']);

        if (isset($filters['unread_only']) && $filters['unread_only']) {
            $query->where('is_read', false);
        }

        if (isset($filters['type']) && $filters['type']) {
            $query->where('notification_type', $filters['type']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $result = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'data' => $result->items(),
            'total_pages' => $result->lastPage(),
            'current_page' => $result->currentPage(),
            'total' => $result->total()
        ];
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId, int $userId): void
    {
        MusicWorkflowNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(int $userId): void
    {
        MusicWorkflowNotification::where('user_id', $userId)
            ->update(['is_read' => true]);
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStats(): array
    {
        $totalSubmissions = MusicSubmission::count();
        $submissionsByState = MusicSubmission::selectRaw('current_state, COUNT(*) as count')
            ->groupBy('current_state')
            ->pluck('count', 'current_state')
            ->toArray();

        $recentSubmissions = MusicSubmission::with(['song', 'musicArranger'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_submissions' => $totalSubmissions,
            'submissions_by_state' => $submissionsByState,
            'recent_submissions' => $recentSubmissions
        ];
    }

    /**
     * Get analytics data
     */
    public function getAnalytics(): array
    {
        $stats = $this->getWorkflowStats();
        
        // Add more analytics here
        $monthlySubmissions = MusicSubmission::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return array_merge($stats, [
            'monthly_submissions' => $monthlySubmissions
        ]);
    }

    /**
     * Create notification for specific role
     */
    private function createNotification(
        int $submissionId,
        string $role,
        string $type,
        string $title,
        string $message
    ): void {
        Log::info('Creating notification:', [
            'submission_id' => $submissionId,
            'role' => $role,
            'type' => $type,
            'title' => $title,
            'message' => $message
        ]);
        
        $users = User::where('role', $role)->get();
        Log::info('Found users for role ' . $role . ':', $users->pluck('id', 'name')->toArray());

        foreach ($users as $user) {
            Log::info('Creating notification for user:', ['user_id' => $user->id, 'user_name' => $user->name]);
            
            try {
                $notification = MusicWorkflowNotification::create([
                    'submission_id' => $submissionId,
                    'user_id' => $user->id,
                    'notification_type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false
                ]);
                Log::info('Notification created successfully:', ['notification_id' => $notification->id]);

                // Broadcast real-time notification
                try {
                    broadcast(new MusicWorkflowNotificationSent($notification));
                    Log::info('Broadcast sent successfully');
                } catch (\Exception $e) {
                    Log::warning('Broadcast failed:', ['error' => $e->getMessage()]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create notification:', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                throw $e;
            }
        }
    }

    /**
     * Create state-specific notifications
     */
    private function createStateNotifications(MusicSubmission $submission, string $newState, string $oldState): void
    {
        $role = $this->getRoleForState($newState);
        
        $messages = [
            'submitted' => 'New music submission received',
            'producer_review' => 'Music submission needs producer review',
            'arranging' => 'Music arrangement in progress',
            'arrangement_review' => 'Arrangement ready for producer review',
            'sound_engineering' => 'Sound engineering in progress',
            'quality_control' => 'Quality control needed',
            'creative_work' => 'Creative work needed',
            'creative_review' => 'Creative work ready for producer review',
            'producer_final_review' => 'Producer final review needed',
            'manager_approval' => 'Manager approval needed for budget and crew',
            'general_affairs' => 'General affairs needs to release funds',
            'promotion' => 'Promotion work needed',
            'production' => 'Production work needed',
            'sound_engineering_final' => 'Final sound engineering needed',
            'final_approval' => 'Final approval needed',
            'completed' => 'Music workflow completed',
            'rejected' => 'Music workflow rejected'
        ];

        $this->createNotification(
            $submission->id,
            $role,
            'state_change',
            'Workflow State Changed',
            $messages[$newState] ?? 'Workflow state changed'
        );
    }

    /**
     * Get role for state
     */
    private function getRoleForState(string $state): string
    {
        $roleMap = [
            'submitted' => 'Producer',
            'producer_review' => 'Producer',
            'arranging' => 'Music Arranger',
            'arrangement_review' => 'Producer',
            'sound_engineering' => 'Sound Engineer',
            'quality_control' => 'Producer',
            'creative_work' => 'Creative',
            'creative_review' => 'Producer',
            'producer_final_review' => 'Producer',
            'manager_approval' => 'Manager Program',
            'general_affairs' => 'General Affairs',
            'promotion' => 'Promotion',
            'production' => 'Production',
            'sound_engineering_final' => 'Sound Engineer',
            'final_approval' => 'Producer',
            'completed' => 'Producer',
            'rejected' => 'Producer'
        ];

        return $roleMap[$state] ?? 'Producer';
    }
}
