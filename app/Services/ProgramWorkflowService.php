<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Deadline;
use App\Models\WorkflowState;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProgramWorkflowService
{
    /**
     * Create new program dan auto-generate 53 episodes
     * Episode otomatis ter-generate saat create program
     */
    public function createProgram(array $data): Program
    {
        return DB::transaction(function () use ($data) {
            // Create program
            $program = Program::create($data);
            
            // Auto-generate 53 episodes saat create program
            // Menggunakan method generateEpisodes() dengan regenerate=false
            // Jika episode sudah ada (tidak mungkin untuk program baru), tidak akan generate lagi
            $program->generateEpisodes(false);
            
            // Create initial workflow state untuk 10 episode pertama
            $this->createInitialWorkflowState($program);
            
            // Send notifications
            $this->sendProgramCreatedNotifications($program);
            
            return $program;
        });
    }

    /**
     * Submit program for approval
     */
    public function submitProgram(Program $program, int $submittedBy): Program
    {
        return DB::transaction(function () use ($program, $submittedBy) {
            $program->update([
                'status' => 'pending_approval',
                'submitted_by' => $submittedBy,
                'submitted_at' => now()
            ]);
            
            // Send notification to Manager Broadcasting
            $this->sendProgramSubmittedNotification($program);
            
            return $program;
        });
    }

    /**
     * Approve program
     */
    public function approveProgram(Program $program, int $approvedBy, ?string $notes = null): Program
    {
        return DB::transaction(function () use ($program, $approvedBy, $notes) {
            $program->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'approval_notes' => $notes
            ]);
            
            // Update all episodes to approved_for_production and set initial workflow state
            $program->episodes()->update([
                'status' => 'approved_for_production',
                'current_workflow_state' => 'episode_generated',
                'assigned_to_role' => 'manager_program',
                'assigned_to_user' => $program->manager_program_id
            ]);
            
            // Create workflow state for all episodes that don't have one yet
            $episodesWithoutState = $program->episodes()
                ->whereDoesntHave('workflowStates')
                ->get();
                
            foreach ($episodesWithoutState as $episode) {
                WorkflowState::create([
                    'episode_id' => $episode->id,
                    'current_state' => 'episode_generated',
                    'assigned_to_role' => 'manager_program',
                    'assigned_to_user_id' => $program->manager_program_id,
                    'notes' => 'Episode generated, ready for production workflow'
                ]);
            }
            
            // Send notifications
            $this->sendProgramApprovedNotifications($program);
            
            return $program;
        });
    }

    /**
     * Reject program
     */
    public function rejectProgram(Program $program, int $rejectedBy, string $reason): Program
    {
        return DB::transaction(function () use ($program, $rejectedBy, $reason) {
            $program->update([
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'rejection_notes' => $reason
            ]);
            
            // Send notifications
            $this->sendProgramRejectedNotifications($program);
            
            return $program;
        });
    }

    /**
     * Update episode workflow state
     */
    public function updateEpisodeWorkflowState(Episode $episode, string $newState, string $assignedRole, ?int $assignedUserId = null): Episode
    {
        return DB::transaction(function () use ($episode, $newState, $assignedRole, $assignedUserId) {
            // Update episode
            $episode->update([
                'current_workflow_state' => $newState,
                'assigned_to_role' => $assignedRole,
                'assigned_to_user' => $assignedUserId
            ]);
            
            // Create workflow state record
            WorkflowState::create([
                'episode_id' => $episode->id,
                'current_state' => $newState,
                'assigned_to_role' => $assignedRole,
                'assigned_to_user_id' => $assignedUserId,
                'notes' => "Workflow state updated to {$newState}"
            ]);
            
            // Send notifications
            $this->sendWorkflowStateChangeNotifications($episode, $newState, $assignedRole, $assignedUserId);
            
            return $episode;
        });
    }

    /**
     * Complete episode
     */
    public function completeEpisode(Episode $episode): Episode
    {
        return DB::transaction(function () use ($episode) {
            $episode->update([
                'status' => 'aired',
                'current_workflow_state' => 'completed'
            ]);
            
            // Create final workflow state
            WorkflowState::create([
                'episode_id' => $episode->id,
                'current_state' => 'completed',
                'assigned_to_role' => 'system',
                'assigned_to_user_id' => null,
                'notes' => 'Episode completed and aired'
            ]);
            
            // Send completion notifications
            $this->sendEpisodeCompletedNotifications($episode);
            
            return $episode;
        });
    }

    /**
     * Get episode progress
     */
    public function getEpisodeProgress(Episode $episode): array
    {
        $deadlines = $episode->deadlines()->get();
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        
        $progressPercentage = $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0;
        
        return [
            'episode_id' => $episode->id,
            'episode_number' => $episode->episode_number,
            'title' => $episode->title,
            'status' => $episode->status,
            'current_workflow_state' => $episode->current_workflow_state,
            'progress_percentage' => $progressPercentage,
            'total_deadlines' => $totalDeadlines,
            'completed_deadlines' => $completedDeadlines,
            'overdue_deadlines' => $deadlines->where('status', 'overdue')->count(),
            'deadlines' => $deadlines->map(function ($deadline) {
                return [
                    'id' => $deadline->id,
                    'role' => $deadline->role,
                    'role_label' => $deadline->role_label,
                    'deadline_date' => $deadline->deadline_date,
                    'status' => $deadline->status,
                    'is_completed' => $deadline->is_completed,
                    'is_overdue' => $deadline->isOverdue()
                ];
            })
        ];
    }

    /**
     * Get program analytics
     */
    public function getProgramAnalytics(Program $program): array
    {
        $episodes = $program->episodes;
        $totalEpisodes = $episodes->count();
        $completedEpisodes = $episodes->where('status', 'aired')->count();
        $inProgressEpisodes = $episodes->whereIn('status', ['draft', 'approved_for_production', 'in_production', 'ready_for_review'])->count();
        
        $deadlines = $program->episodes()->with('deadlines')->get()->pluck('deadlines')->flatten();
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        $overdueDeadlines = $deadlines->where('status', 'overdue')->count();
        
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
     * Create initial workflow state for all episodes
     */
    private function createInitialWorkflowState(Program $program): void
    {
        // Create workflow state for all episodes (or at least first few episodes)
        $episodes = $program->episodes()->orderBy('episode_number')->limit(10)->get(); // Limit to first 10 episodes to avoid too many records
        
        foreach ($episodes as $episode) {
            WorkflowState::create([
                'episode_id' => $episode->id,
                'current_state' => 'program_created',
                'assigned_to_role' => 'manager_program',
                'assigned_to_user_id' => $program->manager_program_id,
                'notes' => 'Program created, ready for production'
            ]);
        }
        
        // For remaining episodes, create workflow state on-demand when needed
    }

    /**
     * Send program created notifications
     */
    private function sendProgramCreatedNotifications(Program $program): void
    {
        // Notify Manager Program
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'program_created',
            'title' => 'Program Created',
            'message' => "Program '{$program->name}' has been created successfully.",
            'program_id' => $program->id,
            'priority' => 'normal'
        ]);
        
        // Notify Production Team
        if ($program->production_team_id) {
            $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'program_created',
                    'title' => 'New Program Assigned',
                    'message' => "You have been assigned to program '{$program->name}'.",
                    'program_id' => $program->id,
                    'priority' => 'normal'
                ]);
            }
        }
    }

    /**
     * Send program submitted notification
     */
    private function sendProgramSubmittedNotification(Program $program): void
    {
        // Find Manager Broadcasting users (Distribution Manager)
        $managerBroadcastingUsers = User::where('role', 'Distribution Manager')->get();
        
        if ($managerBroadcastingUsers->isEmpty()) {
            // Fallback: try alternative role names
            $managerBroadcastingUsers = User::whereIn('role', ['Manager Broadcasting', 'manager_broadcasting', 'Distribution Manager'])->get();
        }
        
        foreach ($managerBroadcastingUsers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'program_submitted',
                'title' => 'Program Submitted for Approval',
                'message' => "Program '{$program->name}' has been submitted for approval by Manager Program.",
                'program_id' => $program->id,
                'priority' => 'high'
            ]);
        }
    }
        
    /**
     * Send program approved notifications
     */
    private function sendProgramApprovedNotifications(Program $program): void
    {
        // Notify Manager Program
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'program_approved',
            'title' => 'Program Approved',
            'message' => "Program '{$program->name}' has been approved and is ready for production.",
            'program_id' => $program->id,
            'priority' => 'high'
        ]);
        
        // Notify Production Team
        if ($program->production_team_id) {
            $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'program_approved',
                    'title' => 'Program Approved - Start Production',
                    'message' => "Program '{$program->name}' has been approved. You can now start production.",
                    'program_id' => $program->id,
                    'priority' => 'high'
                ]);
            }
        }
    }

    /**
     * Send program rejected notifications
     */
    private function sendProgramRejectedNotifications(Program $program): void
    {
        // Notify Manager Program
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'program_rejected',
            'title' => 'Program Rejected',
            'message' => "Program '{$program->name}' has been rejected. Please review and resubmit.",
            'program_id' => $program->id,
            'priority' => 'high'
        ]);
    }

    /**
     * Send workflow state change notifications
     */
    private function sendWorkflowStateChangeNotifications(Episode $episode, string $newState, string $assignedRole, ?int $assignedUserId = null): void
    {
        if ($assignedUserId) {
            Notification::create([
                'user_id' => $assignedUserId,
                'type' => 'workflow_state_change',
                'title' => 'New Task Assigned',
                'message' => "You have been assigned to work on Episode {$episode->episode_number}: {$episode->title}",
                'episode_id' => $episode->id,
                'program_id' => $episode->program_id,
                'priority' => 'normal'
            ]);
        }
    }

    /**
     * Send episode completed notifications
     */
    private function sendEpisodeCompletedNotifications(Episode $episode): void
    {
        // Notify Manager Program
        Notification::create([
            'user_id' => $episode->program->manager_program_id,
            'type' => 'episode_completed',
            'title' => 'Episode Completed',
            'message' => "Episode {$episode->episode_number}: {$episode->title} has been completed and aired.",
            'episode_id' => $episode->id,
            'program_id' => $episode->program_id,
            'priority' => 'normal'
        ]);
    }
}