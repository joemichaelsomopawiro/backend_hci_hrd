<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\ProgramNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WorkflowStateMachine
{
    // Program States
    const PROGRAM_STATES = [
        'draft' => 'Draft',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'active' => 'Active',
        'in_production' => 'In Production',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'auto_closed' => 'Auto Closed'
    ];

    // Episode States
    const EPISODE_STATES = [
        'draft' => 'Draft',
        'script_writing' => 'Script Writing',
        'script_review' => 'Script Review',
        'script_approved' => 'Script Approved',
        'rundown_pending_approval' => 'Rundown Pending Approval',
        'rundown_approved' => 'Rundown Approved',
        'pre_production' => 'Pre Production',
        'production' => 'Production',
        'post_production' => 'Post Production',
        'review' => 'Review',
        'approved_for_air' => 'Approved for Air',
        'aired' => 'Aired',
        'script_overdue' => 'Script Overdue',
        'production_overdue' => 'Production Overdue',
        'rundown_rejected' => 'Rundown Rejected'
    ];

    // Schedule States
    const SCHEDULE_STATES = [
        'draft' => 'Draft',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'overdue' => 'Overdue',
        'rejected' => 'Rejected'
    ];

    // Workflow Steps untuk setiap Role
    const WORKFLOW_STEPS = [
        'manager_program' => [
            'create_program' => [
                'name' => 'Create Program',
                'description' => 'Create new program and assign teams',
                'next_states' => ['draft'],
                'required_fields' => ['name', 'description', 'type']
            ],
            'submit_for_approval' => [
                'name' => 'Submit for Approval',
                'description' => 'Submit program for management approval',
                'next_states' => ['pending_approval'],
                'required_fields' => ['submission_notes']
            ],
            'approve_program' => [
                'name' => 'Approve Program',
                'description' => 'Approve program and set deadlines',
                'next_states' => ['approved', 'active'],
                'required_fields' => ['approval_notes']
            ],
            'monitor_progress' => [
                'name' => 'Monitor Progress',
                'description' => 'Monitor all team progress and KPIs',
                'next_states' => ['active', 'in_production'],
                'required_fields' => []
            ]
        ],
        'producer' => [
            'receive_program' => [
                'name' => 'Receive Program',
                'description' => 'Receive assigned program from manager',
                'next_states' => ['active'],
                'required_fields' => []
            ],
            'review_rundown' => [
                'name' => 'Review Rundown',
                'description' => 'Review and approve episode rundown',
                'next_states' => ['rundown_approved', 'rundown_rejected'],
                'required_fields' => ['approval_notes']
            ],
            'control_production' => [
                'name' => 'Control Production',
                'description' => 'Control and monitor production process',
                'next_states' => ['production', 'post_production'],
                'required_fields' => []
            ]
        ],
        'creative' => [
            'write_script' => [
                'name' => 'Write Script',
                'description' => 'Write episode script and export in various formats',
                'next_states' => ['script_writing'],
                'required_fields' => ['script_content']
            ],
            'submit_script' => [
                'name' => 'Submit Script',
                'description' => 'Submit script for review',
                'next_states' => ['script_review'],
                'required_fields' => ['script_file']
            ],
            'add_guests' => [
                'name' => 'Add Guests',
                'description' => 'Add narasumber and host data',
                'next_states' => ['script_writing'],
                'required_fields' => ['guest_data']
            ],
            'create_schedule' => [
                'name' => 'Create Schedule',
                'description' => 'Create shooting schedule and location',
                'next_states' => ['draft'],
                'required_fields' => ['schedule_data']
            ]
        ],
        'promotion' => [
            'create_bts' => [
                'name' => 'Create BTS Content',
                'description' => 'Create behind the scenes video and photos',
                'next_states' => ['production'],
                'required_fields' => ['bts_files']
            ],
            'upload_files' => [
                'name' => 'Upload Files',
                'description' => 'Upload files to storage and update system',
                'next_states' => ['production'],
                'required_fields' => ['file_urls']
            ]
        ],
        'design_graphis' => [
            'receive_assets' => [
                'name' => 'Receive Assets',
                'description' => 'Receive production files and talent photos',
                'next_states' => ['pre_production'],
                'required_fields' => []
            ],
            'create_thumbnails' => [
                'name' => 'Create Thumbnails',
                'description' => 'Create YouTube and BTS thumbnails',
                'next_states' => ['post_production'],
                'required_fields' => ['thumbnail_files']
            ]
        ],
        'production' => [
            'request_equipment' => [
                'name' => 'Request Equipment',
                'description' => 'Request equipment from Art & Set Properti',
                'next_states' => ['pre_production'],
                'required_fields' => ['equipment_list']
            ],
            'shoot_episode' => [
                'name' => 'Shoot Episode',
                'description' => 'Execute shooting and input notes',
                'next_states' => ['production'],
                'required_fields' => ['shooting_notes']
            ]
        ],
        'art_set_properti' => [
            'provide_equipment' => [
                'name' => 'Provide Equipment',
                'description' => 'Provide and manage production equipment',
                'next_states' => ['approved'],
                'required_fields' => ['equipment_status']
            ],
            'return_equipment' => [
                'name' => 'Return Equipment',
                'description' => 'Collect and return equipment after shooting',
                'next_states' => ['completed'],
                'required_fields' => ['return_condition']
            ]
        ],
        'editor' => [
            'check_files' => [
                'name' => 'Check Files',
                'description' => 'Check file completeness and note missing files',
                'next_states' => ['post_production'],
                'required_fields' => ['file_checklist']
            ],
            'edit_content' => [
                'name' => 'Edit Content',
                'description' => 'Edit content and upload to storage',
                'next_states' => ['review'],
                'required_fields' => ['edited_files']
            ],
            'submit_final' => [
                'name' => 'Submit Final',
                'description' => 'Submit final edited content',
                'next_states' => ['approved_for_air'],
                'required_fields' => ['final_urls']
            ]
        ]
    ];

    /**
     * Get available transitions for current state and role
     */
    public function getAvailableTransitions(string $entityType, string $currentState, string $userRole): array
    {
        $workflowSteps = self::WORKFLOW_STEPS[$userRole] ?? [];
        $availableTransitions = [];

        foreach ($workflowSteps as $stepKey => $step) {
            if (in_array($currentState, $step['next_states']) || empty($step['next_states'])) {
                $availableTransitions[$stepKey] = $step;
            }
        }

        return $availableTransitions;
    }

    /**
     * Execute workflow transition
     */
    public function executeTransition(string $entityType, int $entityId, string $transition, string $userRole, array $data = []): bool
    {
        try {
            $entity = $this->getEntity($entityType, $entityId);
            $currentState = $entity->status;
            
            // Check if transition is valid
            $availableTransitions = $this->getAvailableTransitions($entityType, $currentState, $userRole);
            
            if (!isset($availableTransitions[$transition])) {
                throw new \Exception("Invalid transition '{$transition}' for current state '{$currentState}' and role '{$userRole}'");
            }

            $transitionData = $availableTransitions[$transition];
            
            // Validate required fields
            $this->validateRequiredFields($transitionData['required_fields'], $data);

            // Execute transition based on entity type
            switch ($entityType) {
                case 'program':
                    return $this->executeProgramTransition($entity, $transition, $data);
                case 'episode':
                    return $this->executeEpisodeTransition($entity, $transition, $data);
                case 'schedule':
                    return $this->executeScheduleTransition($entity, $transition, $data);
                default:
                    throw new \Exception("Unsupported entity type: {$entityType}");
            }
        } catch (\Exception $e) {
            Log::error("Workflow transition error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute program transition
     */
    private function executeProgramTransition(Program $program, string $transition, array $data): bool
    {
        switch ($transition) {
            case 'create_program':
                $program->update([
                    'status' => 'draft',
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'type' => $data['type']
                ]);
                break;

            case 'submit_for_approval':
                $program->update([
                    'status' => 'pending_approval',
                    'submission_notes' => $data['submission_notes'],
                    'submitted_at' => now(),
                    'submitted_by' => auth()->id()
                ]);
                $this->notifyForApproval($program, 'program');
                break;

            case 'approve_program':
                $program->update([
                    'status' => 'approved',
                    'approval_notes' => $data['approval_notes'],
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
                $this->notifyTeamMembers($program, 'program_approved');
                break;

            case 'monitor_progress':
                // Update program status based on episodes
                $episodes = $program->episodes;
                if ($episodes->where('status', 'production')->count() > 0) {
                    $program->update(['status' => 'in_production']);
                } else {
                    $program->update(['status' => 'active']);
                }
                break;
        }

        return true;
    }

    /**
     * Execute episode transition
     */
    private function executeEpisodeTransition(Episode $episode, string $transition, array $data): bool
    {
        switch ($transition) {
            case 'write_script':
                $episode->update([
                    'status' => 'script_writing',
                    'script' => $data['script_content']
                ]);
                break;

            case 'submit_script':
                $episode->update([
                    'status' => 'script_review',
                    'script_file' => $data['script_file']
                ]);
                $this->notifyForReview($episode, 'script');
                break;

            case 'review_rundown':
                $action = $data['action'] ?? 'approve';
                if ($action === 'approve') {
                    $episode->update([
                        'status' => 'rundown_approved',
                        'approval_notes' => $data['approval_notes'],
                        'approved_by' => auth()->id(),
                        'approved_at' => now()
                    ]);
                } else {
                    $episode->update([
                        'status' => 'rundown_rejected',
                        'rejection_notes' => $data['rejection_notes'],
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now()
                    ]);
                }
                break;

            case 'control_production':
                $episode->update(['status' => 'production']);
                $this->notifyTeamMembers($episode->program, 'production_started');
                break;
        }

        return true;
    }

    /**
     * Execute schedule transition
     */
    private function executeScheduleTransition(Schedule $schedule, string $transition, array $data): bool
    {
        switch ($transition) {
            case 'create_schedule':
                $schedule->update([
                    'status' => 'draft',
                    'title' => $data['schedule_data']['title'],
                    'description' => $data['schedule_data']['description'],
                    'scheduled_at' => $data['schedule_data']['scheduled_at'],
                    'location' => $data['schedule_data']['location']
                ]);
                break;

            case 'submit_for_approval':
                $schedule->update([
                    'status' => 'pending_approval',
                    'submission_notes' => $data['submission_notes'],
                    'submitted_at' => now(),
                    'submitted_by' => auth()->id()
                ]);
                $this->notifyForApproval($schedule, 'schedule');
                break;

            case 'approve_schedule':
                $schedule->update([
                    'status' => 'approved',
                    'approval_notes' => $data['approval_notes'],
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
                break;
        }

        return true;
    }

    /**
     * Get entity by type and ID
     */
    private function getEntity(string $entityType, int $entityId)
    {
        switch ($entityType) {
            case 'program':
                return Program::findOrFail($entityId);
            case 'episode':
                return Episode::findOrFail($entityId);
            case 'schedule':
                return Schedule::findOrFail($entityId);
            default:
                throw new \Exception("Unsupported entity type: {$entityType}");
        }
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $requiredFields, array $data): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("Required field '{$field}' is missing or empty");
            }
        }
    }

    /**
     * Notify for approval
     */
    private function notifyForApproval($entity, string $type): void
    {
        $notifyUsers = [];
        
        switch ($type) {
            case 'program':
                $notifyUsers = User::whereIn('role', ['Manager', 'Program Manager'])->get();
                break;
            case 'schedule':
                $notifyUsers = User::whereIn('role', ['Manager', 'Program Manager'])->get();
                break;
        }
        
        foreach ($notifyUsers as $user) {
            ProgramNotification::create([
                'title' => ucfirst($type) . ' Pending Approval',
                'message' => "A {$type} is pending your approval: " . ($entity->title ?? $entity->name),
                'type' => 'approval_request',
                'user_id' => $user->id,
                'program_id' => $entity->program_id ?? $entity->id
            ]);
        }
    }

    /**
     * Notify for review
     */
    private function notifyForReview(Episode $episode, string $type): void
    {
        $producers = User::whereIn('role', ['Producer', 'Manager', 'Program Manager'])->get();
        
        foreach ($producers as $producer) {
            ProgramNotification::create([
                'title' => ucfirst($type) . ' Pending Review',
                'message' => "Episode '{$episode->title}' {$type} is pending your review",
                'type' => 'review_request',
                'user_id' => $producer->id,
                'program_id' => $episode->program_id,
                'episode_id' => $episode->id
            ]);
        }
    }

    /**
     * Notify team members
     */
    private function notifyTeamMembers(Program $program, string $action): void
    {
        $teams = $program->teams;
        
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                ProgramNotification::create([
                    'title' => 'Program Update',
                    'message' => "Program '{$program->name}' has been updated: {$action}",
                    'type' => 'program_update',
                    'user_id' => $member->id,
                    'program_id' => $program->id
                ]);
            }
        }
    }

    /**
     * Get workflow status for entity
     */
    public function getWorkflowStatus(string $entityType, int $entityId): array
    {
        $entity = $this->getEntity($entityType, $entityId);
        $userRole = auth()->user()->role ?? 'guest';
        
        return [
            'current_state' => $entity->status,
            'available_transitions' => $this->getAvailableTransitions($entityType, $entity->status, $userRole),
            'workflow_progress' => $this->calculateWorkflowProgress($entityType, $entity)
        ];
    }

    /**
     * Calculate workflow progress percentage
     */
    private function calculateWorkflowProgress(string $entityType, $entity): int
    {
        $totalSteps = count(self::WORKFLOW_STEPS['manager_program']); // Use manager as reference
        $completedSteps = 0;
        
        // Calculate based on current state
        switch ($entity->status) {
            case 'draft':
                $completedSteps = 1;
                break;
            case 'pending_approval':
                $completedSteps = 2;
                break;
            case 'approved':
                $completedSteps = 3;
                break;
            case 'active':
                $completedSteps = 4;
                break;
            case 'in_production':
                $completedSteps = 5;
                break;
            case 'completed':
                $completedSteps = $totalSteps;
                break;
        }
        
        return round(($completedSteps / $totalSteps) * 100);
    }
}

