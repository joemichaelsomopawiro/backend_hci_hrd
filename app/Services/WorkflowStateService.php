<?php

namespace App\Services;

use App\Models\WorkflowState;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkflowStateService
{
    /**
     * Update workflow state
     */
    public function updateWorkflowState(Episode $episode, string $newState, string $assignedRole, ?int $assignedUserId = null, ?string $notes = null): WorkflowState
    {
        return DB::transaction(function () use ($episode, $newState, $assignedRole, $assignedUserId, $notes) {
            // Update episode
            $episode->update([
                'current_workflow_state' => $newState,
                'assigned_to_role' => $assignedRole,
                'assigned_to_user' => $assignedUserId
            ]);
            
            // Create workflow state record
            $workflowState = WorkflowState::create([
                'episode_id' => $episode->id,
                'current_state' => $newState,
                'assigned_to_role' => $assignedRole,
                'assigned_to_user_id' => $assignedUserId,
                'notes' => $notes
            ]);
            
            // Send notifications
            $this->sendWorkflowStateChangeNotifications($episode, $newState, $assignedRole, $assignedUserId);
            
            return $workflowState;
        });
    }

    /**
     * Get workflow history
     */
    public function getWorkflowHistory(Episode $episode): array
    {
        $workflowStates = $episode->workflowStates()
            ->with('assignedToUser')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return $workflowStates->map(function ($state) {
            return [
                'id' => $state->id,
                'current_state' => $state->current_state,
                'state_label' => $state->state_label,
                'assigned_to_role' => $state->assigned_to_role,
                'role_label' => $state->role_label,
                'assigned_to_user_id' => $state->assigned_to_user_id,
                'assigned_to_user_name' => $state->assignedToUser?->name,
                'notes' => $state->notes,
                'created_at' => $state->created_at
            ];
        })->toArray();
    }

    /**
     * Get current workflow state
     */
    public function getCurrentWorkflowState(Episode $episode): array
    {
        $currentState = $episode->workflowStates()
            ->with('assignedToUser')
            ->latest()
            ->first();
            
        if (!$currentState) {
            return [
                'current_state' => 'program_created',
                'state_label' => 'Program Created',
                'assigned_to_role' => 'manager_program',
                'role_label' => 'Manager Program',
                'assigned_to_user_id' => $episode->program->manager_program_id,
                'assigned_to_user_name' => $episode->program->managerProgram->name,
                'notes' => 'Initial workflow state',
                'created_at' => $episode->created_at
            ];
        }
        
        return [
            'current_state' => $currentState->current_state,
            'state_label' => $currentState->state_label,
            'assigned_to_role' => $currentState->assigned_to_role,
            'role_label' => $currentState->role_label,
            'assigned_to_user_id' => $currentState->assigned_to_user_id,
            'assigned_to_user_name' => $currentState->assignedToUser?->name,
            'notes' => $currentState->notes,
            'created_at' => $currentState->created_at
        ];
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStatistics(?int $programId = null): array
    {
        $query = WorkflowState::query();
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        $totalStates = $query->count();
        $byState = $query->selectRaw('current_state, COUNT(*) as count')
            ->groupBy('current_state')
            ->get();
            
        $byRole = $query->selectRaw('assigned_to_role, COUNT(*) as count')
            ->groupBy('assigned_to_role')
            ->get();
            
        return [
            'total_states' => $totalStates,
            'by_state' => $byState->map(function ($item) {
                return [
                    'state' => $item->current_state,
                    'count' => $item->count
                ];
            }),
            'by_role' => $byRole->map(function ($item) {
                return [
                    'role' => $item->assigned_to_role,
                    'count' => $item->count
                ];
            })
        ];
    }

    /**
     * Get episodes by workflow state
     */
    public function getEpisodesByWorkflowState(string $state, ?int $programId = null): array
    {
        $query = Episode::where('current_workflow_state', $state);
        
        if ($programId) {
            $query->where('program_id', $programId);
        }
        
        $episodes = $query->with(['program', 'workflowStates' => function ($q) {
            $q->latest()->limit(1);
        }])->get();
        
        return $episodes->map(function ($episode) {
            return [
                'id' => $episode->id,
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'program_name' => $episode->program->name,
                'current_workflow_state' => $episode->current_workflow_state,
                'assigned_to_role' => $episode->assigned_to_role,
                'assigned_to_user' => $episode->assigned_to_user,
                'air_date' => $episode->air_date,
                'status' => $episode->status
            ];
        })->toArray();
    }

    /**
     * Get user workflow tasks
     */
    public function getUserWorkflowTasks(int $userId): array
    {
        $episodes = Episode::where('assigned_to_user', $userId)
            ->with(['program', 'workflowStates' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->get();
            
        return $episodes->map(function ($episode) {
            return [
                'id' => $episode->id,
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'program_name' => $episode->program->name,
                'current_workflow_state' => $episode->current_workflow_state,
                'assigned_to_role' => $episode->assigned_to_role,
                'air_date' => $episode->air_date,
                'status' => $episode->status,
                'workflow_notes' => $episode->workflowStates->first()?->notes
            ];
        })->toArray();
    }

    /**
     * Get workflow state transitions
     */
    public function getWorkflowStateTransitions(): array
    {
        return [
            'program_created' => ['episode_generated'],
            'episode_generated' => ['music_arrangement', 'creative_work'],
            'music_arrangement' => ['creative_work', 'production_planning'],
            'creative_work' => ['production_planning', 'equipment_request'],
            'production_planning' => ['equipment_request', 'shooting_recording'],
            'equipment_request' => ['shooting_recording'],
            'shooting_recording' => ['editing'],
            'editing' => ['quality_control'],
            'quality_control' => ['broadcasting', 'editing'],
            'broadcasting' => ['promotion', 'completed'],
            'promotion' => ['completed'],
            'completed' => []
        ];
    }

    /**
     * Check if workflow state transition is valid
     */
    public function isValidTransition(string $currentState, string $newState): bool
    {
        $transitions = $this->getWorkflowStateTransitions();
        $allowedTransitions = $transitions[$currentState] ?? [];
        
        return in_array($newState, $allowedTransitions);
    }

    /**
     * Get next possible states
     */
    public function getNextPossibleStates(string $currentState): array
    {
        $transitions = $this->getWorkflowStateTransitions();
        return $transitions[$currentState] ?? [];
    }

    /**
     * Get previous possible states
     */
    public function getPreviousPossibleStates(string $currentState): array
    {
        $transitions = $this->getWorkflowStateTransitions();
        $previousStates = [];
        
        foreach ($transitions as $state => $nextStates) {
            if (in_array($currentState, $nextStates)) {
                $previousStates[] = $state;
            }
        }
        
        return $previousStates;
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
        
        // Notify Manager Program
        Notification::create([
            'user_id' => $episode->program->manager_program_id,
            'type' => 'workflow_state_change',
            'title' => 'Workflow State Changed',
            'message' => "Workflow state changed for Episode {$episode->episode_number}: {$episode->title} to {$newState}",
            'episode_id' => $episode->id,
            'program_id' => $episode->program_id,
            'priority' => 'normal'
        ]);
    }

    /**
     * Get workflow state labels
     */
    public function getWorkflowStateLabels(): array
    {
        return [
            'program_created' => 'Program Created',
            'episode_generated' => 'Episode Generated',
            'music_arrangement' => 'Music Arrangement',
            'creative_work' => 'Creative Work',
            'production_planning' => 'Production Planning',
            'equipment_request' => 'Equipment Request',
            'shooting_recording' => 'Shooting & Recording',
            'editing' => 'Editing',
            'quality_control' => 'Quality Control',
            'broadcasting' => 'Broadcasting',
            'promotion' => 'Promotion',
            'completed' => 'Completed'
        ];
    }

    /**
     * Get role labels
     */
    public function getRoleLabels(): array
    {
        return [
            'creative' => 'Creative',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'production' => 'Production',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Properti',
            'graphic_design' => 'Graphic Design',
            'promotion' => 'Promotion',
            'broadcasting' => 'Broadcasting',
            'quality_control' => 'Quality Control',
            'manager_program' => 'Manager Program'
        ];
    }
}
