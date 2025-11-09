<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Deadline;
use App\Models\WorkflowState;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get program analytics
     */
    public function getProgramAnalytics(int $programId): array
    {
        $program = Program::findOrFail($programId);
        $episodes = $program->episodes;
        
        $totalEpisodes = $episodes->count();
        $completedEpisodes = $episodes->where('status', 'aired')->count();
        $inProgressEpisodes = $episodes->whereIn('status', ['planning', 'ready_to_produce', 'in_production', 'post_production'])->count();
        
        $deadlines = $program->episodes()->with('deadlines')->get()->pluck('deadlines')->flatten();
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        $overdueDeadlines = $deadlines->where('status', 'overdue')->count();
        
        $workflowStates = $program->episodes()->with('workflowStates')->get()->pluck('workflowStates')->flatten();
        $workflowDistribution = $workflowStates->groupBy('current_state')->map->count();
        
        return [
            'program_id' => $program->id,
            'program_name' => $program->name,
            'status' => $program->status,
            'progress_percentage' => $totalEpisodes > 0 ? round(($completedEpisodes / $totalEpisodes) * 100, 2) : 0,
            'episodes' => [
                'total' => $totalEpisodes,
                'completed' => $completedEpisodes,
                'in_progress' => $inProgressEpisodes,
                'remaining' => $totalEpisodes - $completedEpisodes
            ],
            'deadlines' => [
                'total' => $totalDeadlines,
                'completed' => $completedDeadlines,
                'overdue' => $overdueDeadlines,
                'completion_rate' => $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0
            ],
            'workflow_distribution' => $workflowDistribution,
            'next_episode' => $program->next_episode ? [
                'id' => $program->next_episode->id,
                'episode_number' => $program->next_episode->episode_number,
                'title' => $program->next_episode->title,
                'air_date' => $program->next_episode->air_date,
                'status' => $program->next_episode->status
            ] : null
        ];
    }

    /**
     * Get user performance analytics
     */
    public function getUserPerformanceAnalytics(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        // Get user's deadlines
        $deadlines = Deadline::whereHas('episode.program', function ($q) use ($userId) {
            $q->where('manager_program_id', $userId);
        })->get();
        
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        $overdueDeadlines = $deadlines->where('status', 'overdue')->count();
        
        // Get user's workflow states
        $workflowStates = WorkflowState::where('assigned_to_user_id', $userId)->get();
        $workflowDistribution = $workflowStates->groupBy('current_state')->map->count();
        
        // Get user's notifications
        $notifications = Notification::where('user_id', $userId)->get();
        $unreadNotifications = $notifications->where('status', 'unread')->count();
        
        return [
            'user_id' => $userId,
            'user_name' => $user->name,
            'role' => $user->role,
            'deadlines' => [
                'total' => $totalDeadlines,
                'completed' => $completedDeadlines,
                'overdue' => $overdueDeadlines,
                'completion_rate' => $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0
            ],
            'workflow_distribution' => $workflowDistribution,
            'notifications' => [
                'total' => $notifications->count(),
                'unread' => $unreadNotifications,
                'read' => $notifications->count() - $unreadNotifications
            ]
        ];
    }

    /**
     * Get system analytics
     */
    public function getSystemAnalytics(): array
    {
        $totalPrograms = Program::count();
        $activePrograms = Program::whereIn('status', ['approved', 'in_production'])->count();
        $completedPrograms = Program::where('status', 'completed')->count();
        
        $totalEpisodes = Episode::count();
        $completedEpisodes = Episode::where('status', 'aired')->count();
        $inProgressEpisodes = Episode::whereIn('status', ['planning', 'ready_to_produce', 'in_production', 'post_production'])->count();
        
        $totalDeadlines = Deadline::count();
        $completedDeadlines = Deadline::where('is_completed', true)->count();
        $overdueDeadlines = Deadline::where('status', 'overdue')->count();
        
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        
        $totalFiles = MediaFile::count();
        $totalFileSize = MediaFile::sum('file_size');
        
        return [
            'programs' => [
                'total' => $totalPrograms,
                'active' => $activePrograms,
                'completed' => $completedPrograms
            ],
            'episodes' => [
                'total' => $totalEpisodes,
                'completed' => $completedEpisodes,
                'in_progress' => $inProgressEpisodes
            ],
            'deadlines' => [
                'total' => $totalDeadlines,
                'completed' => $completedDeadlines,
                'overdue' => $overdueDeadlines,
                'completion_rate' => $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0
            ],
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers
            ],
            'files' => [
                'total' => $totalFiles,
                'total_size' => $totalFileSize,
                'formatted_size' => $this->formatFileSize($totalFileSize)
            ]
        ];
    }

    /**
     * Get deadline analytics
     */
    public function getDeadlineAnalytics(?int $programId = null): array
    {
        $query = Deadline::query();
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        $deadlines = $query->get();
        
        $byRole = $deadlines->groupBy('role')->map(function ($group) {
            return [
                'total' => $group->count(),
                'completed' => $group->where('is_completed', true)->count(),
                'overdue' => $group->where('status', 'overdue')->count(),
                'completion_rate' => $group->count() > 0 ? round(($group->where('is_completed', true)->count() / $group->count()) * 100, 2) : 0
            ];
        });
        
        $byStatus = $deadlines->groupBy('status')->map->count();
        
        return [
            'by_role' => $byRole,
            'by_status' => $byStatus,
            'total' => $deadlines->count(),
            'completed' => $deadlines->where('is_completed', true)->count(),
            'overdue' => $deadlines->where('status', 'overdue')->count(),
            'completion_rate' => $deadlines->count() > 0 ? round(($deadlines->where('is_completed', true)->count() / $deadlines->count()) * 100, 2) : 0
        ];
    }

    /**
     * Get workflow analytics
     */
    public function getWorkflowAnalytics(?int $programId = null): array
    {
        $query = WorkflowState::query();
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        $workflowStates = $query->get();
        
        $byState = $workflowStates->groupBy('current_state')->map->count();
        $byRole = $workflowStates->groupBy('assigned_to_role')->map->count();
        
        return [
            'by_state' => $byState,
            'by_role' => $byRole,
            'total' => $workflowStates->count()
        ];
    }

    /**
     * Get file analytics
     */
    public function getFileAnalytics(?int $programId = null): array
    {
        $query = MediaFile::query();
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        $files = $query->get();
        
        $byType = $files->groupBy('file_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_size' => $group->sum('file_size'),
                'formatted_size' => $this->formatFileSize($group->sum('file_size'))
            ];
        });
        
        $byStatus = $files->groupBy('status')->map->count();
        
        return [
            'by_type' => $byType,
            'by_status' => $byStatus,
            'total' => $files->count(),
            'total_size' => $files->sum('file_size'),
            'formatted_total_size' => $this->formatFileSize($files->sum('file_size'))
        ];
    }

    /**
     * Get notification analytics
     */
    public function getNotificationAnalytics(?int $userId = null): array
    {
        $query = Notification::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        $notifications = $query->get();
        
        $byType = $notifications->groupBy('type')->map->count();
        $byPriority = $notifications->groupBy('priority')->map->count();
        $byStatus = $notifications->groupBy('status')->map->count();
        
        return [
            'by_type' => $byType,
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
            'total' => $notifications->count(),
            'unread' => $notifications->where('status', 'unread')->count(),
            'read' => $notifications->where('status', 'read')->count()
        ];
    }

    /**
     * Get time-based analytics
     */
    public function getTimeBasedAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $endDate = now();
        
        $programs = Program::whereBetween('created_at', [$startDate, $endDate])->get();
        $episodes = Episode::whereBetween('created_at', [$startDate, $endDate])->get();
        $deadlines = Deadline::whereBetween('created_at', [$startDate, $endDate])->get();
        $workflowStates = WorkflowState::whereBetween('created_at', [$startDate, $endDate])->get();
        $files = MediaFile::whereBetween('created_at', [$startDate, $endDate])->get();
        
        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $days
            ],
            'programs' => [
                'created' => $programs->count(),
                'completed' => $programs->where('status', 'completed')->count()
            ],
            'episodes' => [
                'created' => $episodes->count(),
                'completed' => $episodes->where('status', 'aired')->count()
            ],
            'deadlines' => [
                'created' => $deadlines->count(),
                'completed' => $deadlines->where('is_completed', true)->count(),
                'overdue' => $deadlines->where('status', 'overdue')->count()
            ],
            'workflow_states' => [
                'created' => $workflowStates->count()
            ],
            'files' => [
                'uploaded' => $files->count(),
                'total_size' => $files->sum('file_size'),
                'formatted_size' => $this->formatFileSize($files->sum('file_size'))
            ]
        ];
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get dashboard data
     */
    public function getDashboardData(?int $userId = null): array
    {
        $user = $userId ? User::find($userId) : null;
        
        $data = [
            'system_analytics' => $this->getSystemAnalytics(),
            'deadline_analytics' => $this->getDeadlineAnalytics(),
            'workflow_analytics' => $this->getWorkflowAnalytics(),
            'file_analytics' => $this->getFileAnalytics(),
            'time_based_analytics' => $this->getTimeBasedAnalytics(30)
        ];
        
        if ($user) {
            $data['user_analytics'] = $this->getUserPerformanceAnalytics($userId);
            $data['user_notifications'] = $this->getNotificationAnalytics($userId);
        }
        
        return $data;
    }
}
