<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\ProgramNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProgramWorkflowService
{
    /**
     * Automatically set deadlines for episodes based on air date
     */
    public function setAutomaticDeadlines(Program $program): void
    {
        $episodes = $program->episodes()->whereNull('production_deadline')->get();
        
        foreach ($episodes as $episode) {
            // Set production deadline 3 days before air date
            $productionDeadline = Carbon::parse($episode->air_date)->subDays(3);
            
            // Set script deadline 7 days before air date
            $scriptDeadline = Carbon::parse($episode->air_date)->subDays(7);
            
            $episode->update([
                'production_deadline' => $productionDeadline,
                'script_deadline' => $scriptDeadline
            ]);
            
            // Create notifications for deadlines
            $this->createDeadlineNotifications($episode, $productionDeadline, $scriptDeadline);
        }
    }

    /**
     * Create deadline notifications
     */
    private function createDeadlineNotifications(Episode $episode, Carbon $productionDeadline, Carbon $scriptDeadline): void
    {
        $program = $episode->program;
        
        // Notify Creative team about script deadline
        $creativeUsers = User::where('role', 'Creative')->get();
        foreach ($creativeUsers as $user) {
            ProgramNotification::create([
                'title' => 'Script Deadline Reminder',
                'message' => "Script deadline for episode '{$episode->title}' is on {$scriptDeadline->format('Y-m-d H:i')}",
                'type' => 'deadline_reminder',
                'user_id' => $user->id,
                'program_id' => $program->id,
                'episode_id' => $episode->id,
                'scheduled_at' => $scriptDeadline->subDays(1)
            ]);
        }
        
        // Notify Production team about production deadline
        $productionUsers = User::where('role', 'Production')->get();
        foreach ($productionUsers as $user) {
            ProgramNotification::create([
                'title' => 'Production Deadline Reminder',
                'message' => "Production deadline for episode '{$episode->title}' is on {$productionDeadline->format('Y-m-d H:i')}",
                'type' => 'deadline_reminder',
                'user_id' => $user->id,
                'program_id' => $program->id,
                'episode_id' => $episode->id,
                'scheduled_at' => $productionDeadline->subDays(1)
            ]);
        }
    }

    /**
     * Automatically update episode status based on deadlines
     */
    public function updateEpisodeStatuses(): void
    {
        $episodes = Episode::whereIn('status', ['draft', 'in_production'])
            ->where('air_date', '<=', now())
            ->get();
            
        foreach ($episodes as $episode) {
            if ($episode->status === 'draft' && $episode->script_deadline <= now()) {
                $episode->update(['status' => 'script_overdue']);
                $this->notifyOverdueStatus($episode, 'script');
            }
            
            if ($episode->status === 'in_production' && $episode->production_deadline <= now()) {
                $episode->update(['status' => 'production_overdue']);
                $this->notifyOverdueStatus($episode, 'production');
            }
        }
    }

    /**
     * Notify about overdue status
     */
    private function notifyOverdueStatus(Episode $episode, string $type): void
    {
        $program = $episode->program;
        $manager = $program->manager;
        
        ProgramNotification::create([
            'title' => ucfirst($type) . ' Overdue',
            'message' => "Episode '{$episode->title}' {$type} is overdue. Please take action.",
            'type' => 'overdue_alert',
            'user_id' => $manager->id,
            'program_id' => $program->id,
            'episode_id' => $episode->id
        ]);
    }

    /**
     * Auto-close programs that are not developing
     */
    public function autoCloseInactivePrograms(): void
    {
        $inactivePrograms = Program::where('status', 'active')
            ->where('created_at', '<', now()->subMonths(3))
            ->whereDoesntHave('episodes', function($query) {
                $query->where('created_at', '>', now()->subMonth());
            })
            ->get();
            
        foreach ($inactivePrograms as $program) {
            $program->update(['status' => 'auto_closed']);
            
            // Notify manager
            ProgramNotification::create([
                'title' => 'Program Auto-Closed',
                'message' => "Program '{$program->name}' has been automatically closed due to inactivity.",
                'type' => 'program_closed',
                'user_id' => $program->manager_id,
                'program_id' => $program->id
            ]);
        }
    }

    /**
     * Process approval workflows
     */
    public function processApprovalWorkflow(string $type, int $entityId, string $action, int $approverId, ?string $notes = null): bool
    {
        try {
            switch ($type) {
                case 'rundown':
                    return $this->processRundownApproval($entityId, $action, $approverId, $notes);
                case 'schedule':
                    return $this->processScheduleApproval($entityId, $action, $approverId, $notes);
                case 'program':
                    return $this->processProgramApproval($entityId, $action, $approverId, $notes);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            Log::error("Approval workflow error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process rundown approval
     */
    private function processRundownApproval(int $episodeId, string $action, int $approverId, ?string $notes): bool
    {
        $episode = Episode::findOrFail($episodeId);
        
        if ($action === 'approve') {
            $episode->update([
                'status' => 'approved_for_production',
                'approval_notes' => $notes,
                'approved_by' => $approverId,
                'approved_at' => now()
            ]);
            
            // Notify production team
            $this->notifyTeam('Production', "Episode '{$episode->title}' rundown approved. Ready for production.", $episode);
        } else {
            $episode->update([
                'status' => 'rundown_rejected',
                'rejection_notes' => $notes,
                'rejected_by' => $approverId,
                'rejected_at' => now()
            ]);
            
            // Notify creative team
            $this->notifyTeam('Creative', "Episode '{$episode->title}' rundown rejected. Please revise.", $episode);
        }
        
        return true;
    }

    /**
     * Process schedule approval
     */
    private function processScheduleApproval(int $scheduleId, string $action, int $approverId, ?string $notes): bool
    {
        $schedule = Schedule::findOrFail($scheduleId);
        
        if ($action === 'approve') {
            $schedule->update([
                'status' => 'approved',
                'approval_notes' => $notes,
                'approved_by' => $approverId,
                'approved_at' => now()
            ]);
        } else {
            $schedule->update([
                'status' => 'rejected',
                'rejection_notes' => $notes,
                'rejected_by' => $approverId,
                'rejected_at' => now()
            ]);
        }
        
        return true;
    }

    /**
     * Process program approval
     */
    private function processProgramApproval(int $programId, string $action, int $approverId, ?string $notes): bool
    {
        $program = Program::findOrFail($programId);
        
        if ($action === 'approve') {
            $program->update([
                'status' => 'approved',
                'approval_notes' => $notes,
                'approved_by' => $approverId,
                'approved_at' => now()
            ]);
            
            // Auto-set deadlines for all episodes
            $this->setAutomaticDeadlines($program);
        } else {
            $program->update([
                'status' => 'rejected',
                'rejection_notes' => $notes,
                'rejected_by' => $approverId,
                'rejected_at' => now()
            ]);
        }
        
        return true;
    }

    /**
     * Notify team members
     */
    private function notifyTeam(string $role, string $message, Episode $episode): void
    {
        $users = User::where('role', $role)->get();
        
        foreach ($users as $user) {
            ProgramNotification::create([
                'title' => 'Workflow Update',
                'message' => $message,
                'type' => 'workflow_update',
                'user_id' => $user->id,
                'program_id' => $episode->program_id,
                'episode_id' => $episode->id
            ]);
        }
    }

    /**
     * Send reminder notifications
     */
    public function sendReminderNotifications(): void
    {
        // Get upcoming deadlines
        $upcomingDeadlines = Episode::where('script_deadline', '<=', now()->addDays(1))
            ->orWhere('production_deadline', '<=', now()->addDays(1))
            ->get();
            
        foreach ($upcomingDeadlines as $episode) {
            $this->sendDeadlineReminder($episode);
        }
        
        // Get overdue schedules
        $overdueSchedules = Schedule::where('deadline', '<', now())
            ->where('status', '!=', 'completed')
            ->get();
            
        foreach ($overdueSchedules as $schedule) {
            $this->sendOverdueReminder($schedule);
        }
    }

    /**
     * Send deadline reminder
     */
    private function sendDeadlineReminder(Episode $episode): void
    {
        $program = $episode->program;
        
        if ($episode->script_deadline <= now()->addHours(24)) {
            $creativeUsers = User::where('role', 'Creative')->get();
            foreach ($creativeUsers as $user) {
                ProgramNotification::create([
                    'title' => 'Script Deadline Tomorrow',
                    'message' => "Script deadline for '{$episode->title}' is tomorrow!",
                    'type' => 'urgent_reminder',
                    'user_id' => $user->id,
                    'program_id' => $program->id,
                    'episode_id' => $episode->id
                ]);
            }
        }
        
        if ($episode->production_deadline <= now()->addHours(24)) {
            $productionUsers = User::where('role', 'Production')->get();
            foreach ($productionUsers as $user) {
                ProgramNotification::create([
                    'title' => 'Production Deadline Tomorrow',
                    'message' => "Production deadline for '{$episode->title}' is tomorrow!",
                    'type' => 'urgent_reminder',
                    'user_id' => $user->id,
                    'program_id' => $program->id,
                    'episode_id' => $episode->id
                ]);
            }
        }
    }

    /**
     * Send overdue reminder
     */
    private function sendOverdueReminder(Schedule $schedule): void
    {
        $assignedUser = $schedule->assignedUser;
        if ($assignedUser) {
            ProgramNotification::create([
                'title' => 'Schedule Overdue',
                'message' => "Schedule '{$schedule->title}' is overdue!",
                'type' => 'overdue_alert',
                'user_id' => $assignedUser->id,
                'program_id' => $schedule->program_id,
                'schedule_id' => $schedule->id
            ]);
        }
    }
}
