<?php

namespace App\Services;

use App\Models\ProgramNotification;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send workflow step notification
     */
    public function sendWorkflowNotification(string $type, array $data): void
    {
        try {
            switch ($type) {
                case 'workflow_step_completed':
                    $this->sendWorkflowStepCompleted($data);
                    break;
                case 'deadline_reminder':
                    $this->sendDeadlineReminder($data);
                    break;
                case 'overdue_alert':
                    $this->sendOverdueAlert($data);
                    break;
                case 'approval_request':
                    $this->sendApprovalRequest($data);
                    break;
                case 'team_assignment':
                    $this->sendTeamAssignment($data);
                    break;
                case 'file_uploaded':
                    $this->sendFileUploaded($data);
                    break;
                case 'schedule_reminder':
                    $this->sendScheduleReminder($data);
                    break;
                default:
                    Log::warning("Unknown notification type: {$type}");
            }
        } catch (\Exception $e) {
            Log::error("Error sending notification: " . $e->getMessage());
        }
    }

    /**
     * Send workflow step completed notification
     */
    private function sendWorkflowStepCompleted(array $data): void
    {
        $entity = $data['entity'];
        $step = $data['step'];
        $user = $data['user'];
        $nextStep = $data['next_step'] ?? null;

        // Get team members to notify
        $teamMembers = $this->getTeamMembersForEntity($entity);

        foreach ($teamMembers as $member) {
            $message = "Workflow step '{$step}' completed by {$user->name}";
            if ($nextStep) {
                $message .= ". Next step: {$nextStep}";
            }

            ProgramNotification::create([
                'title' => 'Workflow Step Completed',
                'message' => $message,
                'type' => 'workflow_update',
                'user_id' => $member->id,
                'program_id' => $this->getProgramId($entity),
                'episode_id' => $this->getEpisodeId($entity),
                'schedule_id' => $this->getScheduleId($entity)
            ]);
        }
    }

    /**
     * Send deadline reminder
     */
    private function sendDeadlineReminder(array $data): void
    {
        $entity = $data['entity'];
        $deadlineType = $data['deadline_type'];
        $deadline = $data['deadline'];
        $users = $data['users'];

        foreach ($users as $user) {
            $message = "Reminder: {$deadlineType} deadline is approaching on " . 
                      Carbon::parse($deadline)->format('M d, Y H:i');

            ProgramNotification::create([
                'title' => 'Deadline Reminder',
                'message' => $message,
                'type' => 'deadline_reminder',
                'user_id' => $user->id,
                'program_id' => $this->getProgramId($entity),
                'episode_id' => $this->getEpisodeId($entity),
                'schedule_id' => $this->getScheduleId($entity),
                'scheduled_at' => Carbon::parse($deadline)->subHours(24)
            ]);
        }
    }

    /**
     * Send overdue alert
     */
    private function sendOverdueAlert(array $data): void
    {
        $entity = $data['entity'];
        $overdueType = $data['overdue_type'];
        $overdueDays = $data['overdue_days'] ?? 0;
        $users = $data['users'];

        foreach ($users as $user) {
            $message = "ALERT: {$overdueType} is overdue";
            if ($overdueDays > 0) {
                $message .= " by {$overdueDays} day(s)";
            }

            ProgramNotification::create([
                'title' => 'Overdue Alert',
                'message' => $message,
                'type' => 'overdue_alert',
                'user_id' => $user->id,
                'program_id' => $this->getProgramId($entity),
                'episode_id' => $this->getEpisodeId($entity),
                'schedule_id' => $this->getScheduleId($entity)
            ]);
        }
    }

    /**
     * Send approval request
     */
    private function sendApprovalRequest(array $data): void
    {
        $entity = $data['entity'];
        $approvalType = $data['approval_type'];
        $requestedBy = $data['requested_by'];
        $approvers = $data['approvers'];

        foreach ($approvers as $approver) {
            $message = "{$approvalType} approval requested by {$requestedBy->name}";

            ProgramNotification::create([
                'title' => 'Approval Request',
                'message' => $message,
                'type' => 'approval_request',
                'user_id' => $approver->id,
                'program_id' => $this->getProgramId($entity),
                'episode_id' => $this->getEpisodeId($entity),
                'schedule_id' => $this->getScheduleId($entity)
            ]);
        }
    }

    /**
     * Send team assignment notification
     */
    private function sendTeamAssignment(array $data): void
    {
        $team = $data['team'];
        $assignedUsers = $data['assigned_users'];
        $assignmentType = $data['assignment_type'];

        foreach ($assignedUsers as $user) {
            $message = "You have been assigned to team '{$team->name}' for {$assignmentType}";

            ProgramNotification::create([
                'title' => 'Team Assignment',
                'message' => $message,
                'type' => 'team_assignment',
                'user_id' => $user->id,
                'program_id' => $team->program_id
            ]);
        }
    }

    /**
     * Send file uploaded notification
     */
    private function sendFileUploaded(array $data): void
    {
        $file = $data['file'];
        $entity = $data['entity'];
        $uploader = $data['uploader'];
        $notifyUsers = $data['notify_users'];

        foreach ($notifyUsers as $user) {
            $message = "New {$file->category} file uploaded by {$uploader->name}: {$file->original_name}";

            ProgramNotification::create([
                'title' => 'File Uploaded',
                'message' => $message,
                'type' => 'file_uploaded',
                'user_id' => $user->id,
                'program_id' => $this->getProgramId($entity),
                'episode_id' => $this->getEpisodeId($entity),
                'schedule_id' => $this->getScheduleId($entity)
            ]);
        }
    }

    /**
     * Send schedule reminder
     */
    private function sendScheduleReminder(array $data): void
    {
        $schedule = $data['schedule'];
        $reminderType = $data['reminder_type'];
        $users = $data['users'];

        foreach ($users as $user) {
            $message = "Schedule reminder: '{$schedule->title}' - {$reminderType}";

            ProgramNotification::create([
                'title' => 'Schedule Reminder',
                'message' => $message,
                'type' => 'schedule_reminder',
                'user_id' => $user->id,
                'program_id' => $schedule->program_id,
                'schedule_id' => $schedule->id,
                'scheduled_at' => $schedule->scheduled_at
            ]);
        }
    }

    /**
     * Send daily workflow summary
     */
    public function sendDailyWorkflowSummary(): void
    {
        $users = User::whereIn('role', ['Manager', 'Program Manager', 'Producer'])->get();

        foreach ($users as $user) {
            $summary = $this->generateDailySummary($user);
            
            if (!empty($summary['items'])) {
                ProgramNotification::create([
                    'title' => 'Daily Workflow Summary',
                    'message' => $this->formatSummaryMessage($summary),
                    'type' => 'daily_summary',
                    'user_id' => $user->id,
                    'scheduled_at' => now()->addMinutes(5)
                ]);
            }
        }
    }

    /**
     * Generate daily summary for user
     */
    private function generateDailySummary(User $user): array
    {
        $summary = [
            'overdue_items' => [],
            'due_today' => [],
            'upcoming_deadlines' => [],
            'pending_approvals' => []
        ];

        // Get overdue items
        $summary['overdue_items'] = $this->getOverdueItems($user);
        
        // Get items due today
        $summary['due_today'] = $this->getItemsDueToday($user);
        
        // Get upcoming deadlines
        $summary['upcoming_deadlines'] = $this->getUpcomingDeadlines($user);
        
        // Get pending approvals
        $summary['pending_approvals'] = $this->getPendingApprovals($user);

        return $summary;
    }

    /**
     * Get overdue items for user
     */
    private function getOverdueItems(User $user): array
    {
        $overdueItems = [];

        // Overdue episodes
        $overdueEpisodes = Episode::whereHas('program', function($query) use ($user) {
            $query->where('manager_id', $user->id)
                  ->orWhere('producer_id', $user->id);
        })
        ->where('script_deadline', '<', now())
        ->where('status', '!=', 'script_approved')
        ->get();

        foreach ($overdueEpisodes as $episode) {
            $overdueItems[] = [
                'type' => 'episode_script',
                'title' => $episode->title,
                'deadline' => $episode->script_deadline,
                'overdue_days' => now()->diffInDays($episode->script_deadline)
            ];
        }

        // Overdue schedules
        $overdueSchedules = Schedule::where('assigned_to', $user->id)
            ->where('deadline', '<', now())
            ->where('status', '!=', 'completed')
            ->get();

        foreach ($overdueSchedules as $schedule) {
            $overdueItems[] = [
                'type' => 'schedule',
                'title' => $schedule->title,
                'deadline' => $schedule->deadline,
                'overdue_days' => now()->diffInDays($schedule->deadline)
            ];
        }

        return $overdueItems;
    }

    /**
     * Get items due today
     */
    private function getItemsDueToday(User $user): array
    {
        $dueToday = [];

        // Episodes due today
        $episodesDueToday = Episode::whereHas('program', function($query) use ($user) {
            $query->where('manager_id', $user->id)
                  ->orWhere('producer_id', $user->id);
        })
        ->whereDate('script_deadline', today())
        ->orWhereDate('production_deadline', today())
        ->get();

        foreach ($episodesDueToday as $episode) {
            $dueToday[] = [
                'type' => 'episode',
                'title' => $episode->title,
                'deadline_type' => $episode->script_deadline == today() ? 'script' : 'production',
                'deadline' => $episode->script_deadline == today() ? $episode->script_deadline : $episode->production_deadline
            ];
        }

        return $dueToday;
    }

    /**
     * Get upcoming deadlines
     */
    private function getUpcomingDeadlines(User $user): array
    {
        $upcoming = [];

        // Episodes with deadlines in next 3 days
        $upcomingEpisodes = Episode::whereHas('program', function($query) use ($user) {
            $query->where('manager_id', $user->id)
                  ->orWhere('producer_id', $user->id);
        })
        ->whereBetween('script_deadline', [now()->addDay(), now()->addDays(3)])
        ->orWhereBetween('production_deadline', [now()->addDay(), now()->addDays(3)])
        ->get();

        foreach ($upcomingEpisodes as $episode) {
            $upcoming[] = [
                'type' => 'episode',
                'title' => $episode->title,
                'deadline' => $episode->script_deadline,
                'days_until' => now()->diffInDays($episode->script_deadline)
            ];
        }

        return $upcoming;
    }

    /**
     * Get pending approvals
     */
    private function getPendingApprovals(User $user): array
    {
        $pending = [];

        // Programs pending approval
        if (in_array($user->role, ['Manager', 'Program Manager'])) {
            $pendingPrograms = Program::where('status', 'pending_approval')->get();
            foreach ($pendingPrograms as $program) {
                $pending[] = [
                    'type' => 'program',
                    'title' => $program->name,
                    'submitted_at' => $program->submitted_at
                ];
            }
        }

        // Episodes pending approval
        if (in_array($user->role, ['Producer', 'Manager', 'Program Manager'])) {
            $pendingEpisodes = Episode::where('status', 'rundown_pending_approval')->get();
            foreach ($pendingEpisodes as $episode) {
                $pending[] = [
                    'type' => 'episode',
                    'title' => $episode->title,
                    'submitted_at' => $episode->submitted_at
                ];
            }
        }

        return $pending;
    }

    /**
     * Format summary message
     */
    private function formatSummaryMessage(array $summary): string
    {
        $message = "Daily Workflow Summary:\n\n";

        if (!empty($summary['overdue_items'])) {
            $message .= "🚨 OVERDUE ITEMS:\n";
            foreach ($summary['overdue_items'] as $item) {
                $message .= "• {$item['title']} ({$item['overdue_days']} days overdue)\n";
            }
            $message .= "\n";
        }

        if (!empty($summary['due_today'])) {
            $message .= "📅 DUE TODAY:\n";
            foreach ($summary['due_today'] as $item) {
                $message .= "• {$item['title']} ({$item['deadline_type']})\n";
            }
            $message .= "\n";
        }

        if (!empty($summary['upcoming_deadlines'])) {
            $message .= "⏰ UPCOMING DEADLINES:\n";
            foreach ($summary['upcoming_deadlines'] as $item) {
                $message .= "• {$item['title']} (in {$item['days_until']} days)\n";
            }
            $message .= "\n";
        }

        if (!empty($summary['pending_approvals'])) {
            $message .= "✅ PENDING APPROVALS:\n";
            foreach ($summary['pending_approvals'] as $item) {
                $message .= "• {$item['title']} ({$item['type']})\n";
            }
        }

        return $message;
    }

    /**
     * Get team members for entity
     */
    private function getTeamMembersForEntity($entity): array
    {
        if ($entity instanceof Program) {
            $teams = $entity->teams;
            $members = [];
            foreach ($teams as $team) {
                $members = array_merge($members, $team->members->toArray());
            }
            return $members;
        }

        if ($entity instanceof Episode) {
            return $entity->program->teams->flatMap->members->toArray();
        }

        if ($entity instanceof Schedule) {
            return $entity->program->teams->flatMap->members->toArray();
        }

        return [];
    }

    /**
     * Get program ID from entity
     */
    private function getProgramId($entity): ?int
    {
        if ($entity instanceof Program) {
            return $entity->id;
        }
        if ($entity instanceof Episode) {
            return $entity->program_id;
        }
        if ($entity instanceof Schedule) {
            return $entity->program_id;
        }
        return null;
    }

    /**
     * Get episode ID from entity
     */
    private function getEpisodeId($entity): ?int
    {
        if ($entity instanceof Episode) {
            return $entity->id;
        }
        return null;
    }

    /**
     * Get schedule ID from entity
     */
    private function getScheduleId($entity): ?int
    {
        if ($entity instanceof Schedule) {
            return $entity->id;
        }
        return null;
    }
}

