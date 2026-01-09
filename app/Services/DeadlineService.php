<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeadlineService
{
    /**
     * Check and update overdue deadlines
     */
    public function checkOverdueDeadlines(): array
    {
        $overdueDeadlines = Deadline::where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->get();
            
        $updated = [];
        
        foreach ($overdueDeadlines as $deadline) {
            $deadline->update(['status' => 'overdue']);
            $updated[] = $deadline;
            
            // Send overdue notification
            $this->sendOverdueNotification($deadline);
        }
        
        return $updated;
    }

    /**
     * Send deadline reminders
     */
    public function sendDeadlineReminders(): array
    {
        $deadlines = Deadline::where('deadline_date', '<=', now()->addDay())
            ->where('deadline_date', '>', now())
            ->where('is_completed', false)
            ->where('reminder_sent', false)
            ->get();
            
        $sent = [];
        
        foreach ($deadlines as $deadline) {
            if ($deadline->shouldSendReminder()) {
                $this->sendReminderNotification($deadline);
                $deadline->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => now()
                ]);
                $sent[] = $deadline;
            }
        }
        
        return $sent;
    }

    /**
     * Complete deadline
     */
    public function completeDeadline(int $deadlineId, int $userId, ?string $notes = null): Deadline
    {
        $deadline = Deadline::findOrFail($deadlineId);
        
        return DB::transaction(function () use ($deadline, $userId, $notes) {
            $deadline->markAsCompleted($userId, $notes);
            
            // Send completion notification
            $this->sendCompletionNotification($deadline);
            
            return $deadline;
        });
    }

    /**
     * Get deadline statistics
     */
    public function getDeadlineStatistics(?int $userId = null): array
    {
        $query = Deadline::query();
        
        if ($userId) {
            $query->whereHas('episode.program', function ($q) use ($userId) {
                $q->where('manager_program_id', $userId);
            });
        }
        
        $total = $query->count();
        $completed = $query->where('is_completed', true)->count();
        $overdue = $query->where('status', 'overdue')->count();
        $pending = $query->where('status', 'pending')->count();
        $inProgress = $query->where('status', 'in_progress')->count();
        
        return [
            'total' => $total,
            'completed' => $completed,
            'overdue' => $overdue,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get user deadlines
     */
    public function getUserDeadlines(int $userId, ?string $status = null): array
    {
        $query = Deadline::whereHas('episode.program', function ($q) use ($userId) {
            $q->where('manager_program_id', $userId);
        })->with(['episode', 'episode.program']);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $deadlines = $query->orderBy('deadline_date')->get();
        
        return $deadlines->map(function ($deadline) {
            return [
                'id' => $deadline->id,
                'episode_id' => $deadline->episode_id,
                'episode_number' => $deadline->episode->episode_number,
                'episode_title' => $deadline->episode->title,
                'program_name' => $deadline->episode->program->name,
                'role' => $deadline->role,
                'role_label' => $deadline->role_label,
                'deadline_date' => $deadline->deadline_date,
                'status' => $deadline->status,
                'is_completed' => $deadline->is_completed,
                'is_overdue' => $deadline->isOverdue(),
                'notes' => $deadline->notes,
                'completed_at' => $deadline->completed_at,
                'completed_by' => $deadline->completedBy?->name
            ];
        })->toArray();
    }

    /**
     * Get overdue deadlines
     */
    public function getOverdueDeadlines(?int $userId = null): array
    {
        $query = Deadline::where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->with(['episode', 'episode.program']);
            
        if ($userId) {
            $query->whereHas('episode.program', function ($q) use ($userId) {
                $q->where('manager_program_id', $userId);
            });
        }
        
        $deadlines = $query->orderBy('deadline_date')->get();
        
        return $deadlines->map(function ($deadline) {
            return [
                'id' => $deadline->id,
                'episode_id' => $deadline->episode_id,
                'episode_number' => $deadline->episode->episode_number,
                'episode_title' => $deadline->episode->title,
                'program_name' => $deadline->episode->program->name,
                'role' => $deadline->role,
                'role_label' => $deadline->role_label,
                'deadline_date' => $deadline->deadline_date,
                'days_overdue' => now()->diffInDays($deadline->deadline_date),
                'status' => $deadline->status,
                'notes' => $deadline->notes
            ];
        })->toArray();
    }

    /**
     * Get upcoming deadlines
     */
    public function getUpcomingDeadlines(?int $userId = null, int $days = 7): array
    {
        $query = Deadline::where('deadline_date', '<=', now()->addDays($days))
            ->where('deadline_date', '>=', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->with(['episode', 'episode.program']);
            
        if ($userId) {
            $query->whereHas('episode.program', function ($q) use ($userId) {
                $q->where('manager_program_id', $userId);
            });
        }
        
        $deadlines = $query->orderBy('deadline_date')->get();
        
        return $deadlines->map(function ($deadline) {
            return [
                'id' => $deadline->id,
                'episode_id' => $deadline->episode_id,
                'episode_number' => $deadline->episode->episode_number,
                'episode_title' => $deadline->episode->title,
                'program_name' => $deadline->episode->program->name,
                'role' => $deadline->role,
                'role_label' => $deadline->role_label,
                'deadline_date' => $deadline->deadline_date,
                'days_remaining' => now()->diffInDays($deadline->deadline_date, false),
                'status' => $deadline->status,
                'notes' => $deadline->notes
            ];
        })->toArray();
    }

    /**
     * Send overdue notification
     */
    private function sendOverdueNotification(Deadline $deadline): void
    {
        $episode = $deadline->episode;
        $program = $episode->program;
        
        // Map deadline role to user role
        $roleMapping = [
            'creative' => 'Creative',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'production' => 'Production',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Properti',
            'graphic_design' => 'Graphic Design',
            'promotion' => ['Editor Promotion', 'Promotion'],
            'broadcasting' => 'Broadcasting',
            'quality_control' => 'Quality Control'
        ];

        $userRoles = $roleMapping[$deadline->role] ?? [];
        if (!is_array($userRoles)) {
            $userRoles = [$userRoles];
        }

        // Find all users with matching roles
        $users = \App\Models\User::whereIn('role', $userRoles)->get();

        // Also check production team members
        if ($program->productionTeam) {
            $teamMembers = $program->productionTeam->members()
            ->where('is_active', true)
                ->whereIn('role', [$deadline->role])
                ->get();
            
            foreach ($teamMembers as $member) {
                if ($member->user && !$users->contains('id', $member->user_id)) {
                    $users->push($member->user);
                }
            }
        }

        // Send overdue notification to all relevant users
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'deadline_overdue',
                'title' => 'Deadline Overdue',
                'message' => "Your deadline for Episode {$episode->episode_number}: {$episode->title} ({$deadline->role_label}) is overdue!",
                'episode_id' => $episode->id,
                'program_id' => $program->id,
                'priority' => 'urgent',
                'data' => [
                    'deadline_id' => $deadline->id,
                    'role' => $deadline->role,
                    'deadline_date' => $deadline->deadline_date->toDateTimeString()
                ]
            ]);
        }
        
        // Notify Manager Program
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'deadline_overdue',
            'title' => 'Deadline Overdue',
            'message' => "Deadline overdue for Episode {$episode->episode_number}: {$episode->title} - {$deadline->role_label}",
            'episode_id' => $episode->id,
            'program_id' => $program->id,
            'priority' => 'high',
            'data' => [
                'deadline_id' => $deadline->id,
                'role' => $deadline->role
            ]
        ]);

        // Notify Producer
        if ($program->productionTeam && $program->productionTeam->producer) {
            Notification::create([
                'user_id' => $program->productionTeam->producer_id,
                'type' => 'deadline_overdue_team',
                'title' => 'Team Deadline Overdue',
                'message' => "Team deadline overdue for Episode {$episode->episode_number}: {$episode->title} - {$deadline->role_label}",
                'episode_id' => $episode->id,
                'program_id' => $program->id,
                'priority' => 'high',
                'data' => [
                    'deadline_id' => $deadline->id,
                    'role' => $deadline->role
                ]
            ]);
        }
    }

    /**
     * Send reminder notification to all users with the role
     * Covers all roles in music program workflow
     */
    private function sendReminderNotification(Deadline $deadline): void
    {
        $episode = $deadline->episode;
        $program = $episode->program;
        
        // Map deadline role to user role
        $roleMapping = [
            'creative' => 'Creative',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'production' => 'Production',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Properti',
            'graphic_design' => 'Graphic Design',
            'promotion' => ['Editor Promotion', 'Promotion'],
            'broadcasting' => 'Broadcasting',
            'quality_control' => 'Quality Control'
        ];

        $userRoles = $roleMapping[$deadline->role] ?? [];
        
        // Handle array of roles (e.g., promotion)
        if (!is_array($userRoles)) {
            $userRoles = [$userRoles];
        }

        // Find all users with matching roles
        $users = \App\Models\User::whereIn('role', $userRoles)->get();

        // Also check production team members if program has production team
        if ($program->productionTeam) {
            $teamMembers = $program->productionTeam->members()
            ->where('is_active', true)
                ->whereIn('role', [$deadline->role])
                ->get();
            
            foreach ($teamMembers as $member) {
                if ($member->user && !$users->contains('id', $member->user_id)) {
                    $users->push($member->user);
                }
            }
        }

        // Send notification to all relevant users
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'deadline_reminder',
                'title' => 'Deadline Reminder',
                'message' => "You have a deadline for Episode {$episode->episode_number}: {$episode->title} ({$deadline->role_label}) on {$deadline->deadline_date->format('Y-m-d H:i')}",
                'episode_id' => $episode->id,
                'program_id' => $program->id,
                'priority' => 'high',
                'data' => [
                    'deadline_id' => $deadline->id,
                    'role' => $deadline->role,
                    'deadline_date' => $deadline->deadline_date->toDateTimeString()
                ]
            ]);
        }

        // Also notify Producer for visibility
        if ($program->productionTeam && $program->productionTeam->producer) {
            Notification::create([
                'user_id' => $program->productionTeam->producer_id,
                'type' => 'deadline_reminder_team',
                'title' => 'Team Deadline Reminder',
                'message' => "Team member has deadline for Episode {$episode->episode_number}: {$episode->title} ({$deadline->role_label}) on {$deadline->deadline_date->format('Y-m-d H:i')}",
                'episode_id' => $episode->id,
                'program_id' => $program->id,
                'priority' => 'normal',
                'data' => [
                    'deadline_id' => $deadline->id,
                    'role' => $deadline->role
                ]
            ]);
        }
    }

    /**
     * Send completion notification
     */
    private function sendCompletionNotification(Deadline $deadline): void
    {
        $episode = $deadline->episode;
        $program = $episode->program;
        
        // Notify Manager Program
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'deadline_completed',
            'title' => 'Deadline Completed',
            'message' => "Deadline completed for Episode {$episode->episode_number}: {$episode->title} - {$deadline->role_label}",
            'episode_id' => $episode->id,
            'program_id' => $program->id,
            'priority' => 'normal'
        ]);
    }
}
