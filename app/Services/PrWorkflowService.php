<?php

namespace App\Services;

use App\Models\PrEpisode;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\User;
use App\Constants\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrWorkflowService
{
    /**
     * Initialize workflow untuk episode baru
     * Membuat 10 workflow steps dengan status pending
     */
    public function initializeWorkflow(PrEpisode $episode): void
    {
        DB::transaction(function () use ($episode) {
            $steps = WorkflowStep::getAllSteps();

            foreach ($steps as $stepNumber => $stepInfo) {
                PrEpisodeWorkflowProgress::create([
                    'episode_id' => $episode->id,
                    'workflow_step' => $stepNumber,
                    'step_name' => $stepInfo['name'],
                    'responsible_role' => $stepInfo['role'],
                    'status' => WorkflowStep::STATUS_PENDING,
                ]);
            }

            Log::info('Workflow initialized for episode', [
                'episode_id' => $episode->id,
                'program_id' => $episode->program_id,
                'total_steps' => count($steps)
            ]);
        });
    }

    /**
     * Start a workflow step
     */
    public function startStep(int $episodeId, int $stepNumber, ?int $userId = null): PrEpisodeWorkflowProgress
    {
        $progress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $stepNumber)
            ->firstOrFail();

        if ($progress->status === WorkflowStep::STATUS_COMPLETED) {
            throw new \Exception('Step sudah completed, tidak bisa diubah ke in_progress');
        }

        $progress->update([
            'status' => WorkflowStep::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'assigned_user_id' => $userId
        ]);

        // Log activity
        app(\App\Services\PrActivityLogService::class)->logEpisodeActivity(
            $progress->episode,
            'start_step',
            "Started workflow step {$stepNumber}: {$progress->step_name}",
            ['step' => $stepNumber, 'status' => 'in_progress'],
            $userId
        );

        Log::info('Workflow step started', [
            'episode_id' => $episodeId,
            'step_number' => $stepNumber,
            'user_id' => $userId
        ]);

        return $progress->fresh(['assignedUser', 'episode']);
    }

    /**
     * Complete a workflow step
     */
    public function completeStep(int $episodeId, int $stepNumber, ?string $notes = null): PrEpisodeWorkflowProgress
    {
        $progress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $stepNumber)
            ->firstOrFail();

        if ($progress->status === WorkflowStep::STATUS_COMPLETED) {
            throw new \Exception('Step sudah completed');
        }

        $updateData = [
            'status' => WorkflowStep::STATUS_COMPLETED,
            'completed_at' => now()
        ];

        if ($notes) {
            $updateData['notes'] = $notes;
        }

        // If step was not started before, set started_at to now as well
        if (!$progress->started_at) {
            $updateData['started_at'] = now();
        }

        $progress->update($updateData);

        // Auto-create Promotion Work when Step 4 (Creative Approval) is completed
        if ($stepNumber === 4) {
            $this->autoCreatePromotionWork($episodeId);
        }

        // Log activity
        app(\App\Services\PrActivityLogService::class)->logEpisodeActivity(
            $progress->episode,
            'complete_step',
            "Completed workflow step {$stepNumber}: {$progress->step_name}",
            ['step' => $stepNumber, 'status' => 'completed'],
            null
        );

        Log::info('Workflow step completed', [
            'episode_id' => $episodeId,
            'step_number' => $stepNumber,
            'duration_hours' => $progress->duration
        ]);

        // Auto-start next step if exists
        $this->autoStartNextStep($episodeId, $stepNumber);

        return $progress->fresh(['assignedUser', 'episode']);
    }

    /**
     * Auto-create Promotion Work when Creative is approved (Step 4)
     */
    protected function autoCreatePromotionWork(int $episodeId): void
    {
        try {
            // Check if promotion work already exists for this episode
            $existingWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->first();

            if ($existingWork) {
                Log::info('Promotion work already exists for episode', ['episode_id' => $episodeId]);
                return;
            }

            // Get creative work to retrieve shooting schedule details
            $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)->first();

            if (!$creativeWork) {
                Log::warning('No creative work found for episode', ['episode_id' => $episodeId]);
                return;
            }

            // Get shooting schedule data
            $shootingSchedule = is_string($creativeWork->shooting_schedule)
                ? json_decode($creativeWork->shooting_schedule, true)
                : $creativeWork->shooting_schedule;

            // Create promotion work
            $promotionWork = \App\Models\PrPromotionWork::create([
                'pr_episode_id' => $episodeId,
                'work_type' => 'general', // Default work type
                'status' => 'planning',
                'created_by' => $creativeWork->created_by,
                'shooting_date' => $shootingSchedule['date'] ?? null,
                'shooting_time' => $shootingSchedule['time'] ?? null,
                'location_data' => isset($shootingSchedule['location'])
                    ? json_encode(['location' => $shootingSchedule['location']])
                    : null,
                'shooting_notes' => $shootingSchedule['notes'] ?? null,
            ]);

            Log::info('Auto-created promotion work', [
                'episode_id' => $episodeId,
                'promotion_work_id' => $promotionWork->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to auto-create promotion work', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Assign user to a workflow step
     */
    public function assignUser(int $episodeId, int $stepNumber, int $userId): PrEpisodeWorkflowProgress
    {
        $progress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $stepNumber)
            ->firstOrFail();

        $user = User::findOrFail($userId);

        $progress->update([
            'assigned_user_id' => $userId
        ]);

        // Log activity
        app(\App\Services\PrActivityLogService::class)->logEpisodeActivity(
            $progress->episode,
            'assign_user',
            "Assigned user {$user->name} to step {$stepNumber}: {$progress->step_name}",
            ['step' => $stepNumber, 'assigned_user_id' => $userId],
            null
        );

        Log::info('User assigned to workflow step', [
            'episode_id' => $episodeId,
            'step_number' => $stepNumber,
            'user_id' => $userId,
            'user_name' => $user->name
        ]);

        return $progress->fresh(['assignedUser', 'episode']);
    }

    /**
     * Get workflow visualization data
     */
    /**
     * Get workflow visualization data
     */
    public function getWorkflowVisualization(int $episodeId): array
    {
        $episode = PrEpisode::with([
            'workflowProgress.assignedUser',
            'program.managerProgram',
            'program.crews.user' // Eager load crew and their user details
        ])->findOrFail($episodeId);

        $steps = $episode->workflowProgress->map(function ($progress) use ($episode) {
            // Determine the "assigned user" to display based on Role Logic
            $displayUser = null;

            if ($progress->responsible_role === 'Program Manager') {
                // For Program Manager steps, always show the Program Manager
                $displayUser = [
                    'id' => $episode->program->managerProgram->id,
                    'name' => $episode->program->managerProgram->name,
                    'role' => 'Program Manager' // Force display role
                ];
            } else {
                // For other roles, look for the specific crew member assigned to this role in this program
                // Note: responsible_role in workflow might match the role in PrProgramCrew
                $crewMember = $episode->program->crews->first(function ($crew) use ($progress) {
                    return $crew->role === $progress->responsible_role;
                });

                if ($crewMember && $crewMember->user) {
                    $displayUser = [
                        'id' => $crewMember->user->id,
                        'name' => $crewMember->user->name,
                        'role' => $crewMember->role
                    ];
                } else {
                    // Fallback to manually assigned user if no crew found (legacy support)
                    if ($progress->assignedUser) {
                        $displayUser = [
                            'id' => $progress->assignedUser->id,
                            'name' => $progress->assignedUser->name,
                            'role' => $progress->assignedUser->role
                        ];
                    }
                }
            }

            return [
                'step_number' => $progress->workflow_step,
                'step_name' => $progress->step_name,
                'responsible_role' => $progress->responsible_role,
                'responsible_roles' => $progress->responsible_roles, // Array of roles
                'status' => $progress->status,
                'color' => WorkflowStep::getStatusColor($progress->status),
                'assigned_user' => $displayUser,
                'started_at' => $progress->started_at?->toIso8601String(),
                'completed_at' => $progress->completed_at?->toIso8601String(),
                'duration_hours' => $progress->duration,
                'notes' => $progress->notes
            ];
        });

        // SELF-HEALING: Check for Step 6 (Edit Konten) completion
        // Criteria:
        // 1. Editor Work: 'pending_qc' or 'completed'
        // 2. Promotion Work: 'completed'
        // 3. Editor Promosi Work: 'pending_qc' or 'completed'
        $step6 = $episode->workflowProgress->firstWhere('workflow_step', 6);
        if ($step6 && $step6->status !== 'completed') {
            try {
                $editorWork = \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)->first();
                $promoWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->first();
                $editorPromoWork = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
                $designGrafisWork = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

                $editorReady = $editorWork && in_array($editorWork->status, ['pending_qc', 'completed']);
                $promoReady = $promoWork && $promoWork->status === 'completed';
                $editorPromoReady = $editorPromoWork && in_array($editorPromoWork->status, ['pending_qc', 'completed']);
                $designGrafisReady = $designGrafisWork && in_array($designGrafisWork->status, ['pending_qc', 'completed']);

                if ($editorReady && $promoReady && $editorPromoReady && $designGrafisReady) {
                    $step6->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'notes' => 'Auto-completed: Editor, Promotion, Editor Promotion, and Design Grafis requirements met'
                    ]);

                    // Refresh episode to get updated workflow progress
                    $episode->load('workflowProgress');

                    // Update the steps array with the new status
                    // We need to regenerate the steps array because it was already built above
                    $steps = $episode->workflowProgress->map(function ($progress) use ($episode) {
                        // Determine the "assigned user" to display based on Role Logic
                        $displayUser = null;

                        if ($progress->responsible_role === 'Program Manager') {
                            // For Program Manager steps, always show the Program Manager
                            $displayUser = [
                                'id' => $episode->program->managerProgram->id,
                                'name' => $episode->program->managerProgram->name,
                                'role' => 'Program Manager' // Force display role
                            ];
                        } else {
                            // For other roles, look for the specific crew member assigned to this role in this program
                            // Note: responsible_role in workflow might match the role in PrProgramCrew
                            $crewMember = $episode->program->crews->first(function ($crew) use ($progress) {
                                return $crew->role === $progress->responsible_role;
                            });

                            if ($crewMember && $crewMember->user) {
                                $displayUser = [
                                    'id' => $crewMember->user->id,
                                    'name' => $crewMember->user->name,
                                    'role' => $crewMember->role
                                ];
                            } else {
                                // Fallback to manually assigned user if no crew found (legacy support)
                                if ($progress->assignedUser) {
                                    $displayUser = [
                                        'id' => $progress->assignedUser->id,
                                        'name' => $progress->assignedUser->name,
                                        'role' => $progress->assignedUser->role
                                    ];
                                }
                            }
                        }

                        return [
                            'step_number' => $progress->workflow_step,
                            'step_name' => $progress->step_name,
                            'responsible_role' => $progress->responsible_role,
                            'responsible_roles' => $progress->responsible_roles, // Array of roles
                            'status' => $progress->status,
                            'color' => WorkflowStep::getStatusColor($progress->status),
                            'assigned_user' => $displayUser,
                            'started_at' => $progress->started_at?->toIso8601String(),
                            'completed_at' => $progress->completed_at?->toIso8601String(),
                            'duration_hours' => $progress->duration,
                            'notes' => $progress->notes
                        ];
                    });
                }
            } catch (\Exception $e) {
                Log::error('Error checking Step 6 completion: ' . $e->getMessage());
            }
        }

        // SELF-HEALING: Check for Step 7 (Quality Check Manager Distribusi) completion
        // Criteria: PrManagerDistribusiQcWork status is 'completed' or 'approved'
        $step7 = $episode->workflowProgress->firstWhere('workflow_step', 7);
        if ($step7 && $step7->status !== 'completed') {
            try {
                $qcManagerWork = \App\Models\PrManagerDistribusiQcWork::where('pr_episode_id', $episodeId)->first();
                if ($qcManagerWork && in_array($qcManagerWork->status, ['completed', 'approved'])) {
                    $step7->update([
                        'status' => 'completed',
                        'completed_at' => $qcManagerWork->qc_completed_at ?? now(),
                        'notes' => 'Auto-completed: Manager Distribusi QC is marked as completed/approved'
                    ]);
                    $needRefresh = true;
                }
            } catch (\Exception $e) {
                Log::error('Error checking Step 7 completion: ' . $e->getMessage());
            }
        }

        // SELF-HEALING: Check for Step 8 (Quality Check Final) completion
        // Criteria: PrQualityControlWork status is 'completed' or 'approved'
        $step8 = $episode->workflowProgress->firstWhere('workflow_step', 8);
        if ($step8 && $step8->status !== 'completed') {
            try {
                $qcFinalWork = \App\Models\PrQualityControlWork::where('pr_episode_id', $episodeId)->first();
                if ($qcFinalWork && in_array($qcFinalWork->status, ['completed', 'approved'])) {
                    $step8->update([
                        'status' => 'completed',
                        'completed_at' => $qcFinalWork->qc_completed_at ?? now(),
                        'notes' => 'Auto-completed: Final QC is marked as completed/approved'
                    ]);
                    $needRefresh = true;
                }
            } catch (\Exception $e) {
                Log::error('Error checking Step 8 completion: ' . $e->getMessage());
            }
        }

        $step3 = $episode->workflowProgress->firstWhere('workflow_step', 3);
        if ($step3 && $step3->status !== 'completed') {
            $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)->first();
            // Check if work exists and is in a "submitted" or further state
            // Statuses: 'draft', 'in_progress', 'submitted', 'revised', 'approved', 'rejected'
            // We consider 'submitted' and 'approved' as completed for the workflow step purpose
            if ($creativeWork && in_array($creativeWork->status, ['submitted', 'approved'])) {
                $step3->update([
                    'status' => 'completed',
                    'completed_at' => $creativeWork->reviewed_at ?? $creativeWork->updated_at ?? now(),
                    'notes' => 'Auto-completed by system (Self-Healing)'
                ]);

                $needRefresh = true;
            }
        }

        if (isset($needRefresh) && $needRefresh) {
            // Refresh the steps collection to reflect the change
            $episode->load('workflowProgress');
            // Re-map steps
            $steps = $episode->workflowProgress->map(function ($progress) use ($episode) {
                // Determine the "assigned user" to display based on Role Logic
                $displayUser = null;

                if ($progress->responsible_role === 'Program Manager') {
                    // For Program Manager steps, always show the Program Manager
                    $displayUser = [
                        'id' => $episode->program->managerProgram->id,
                        'name' => $episode->program->managerProgram->name,
                        'role' => 'Program Manager' // Force display role
                    ];
                } else {
                    // For other roles, look for the specific crew member assigned to this role in this program
                    // Note: responsible_role in workflow might match the role in PrProgramCrew
                    $crewMember = $episode->program->crews->first(function ($crew) use ($progress) {
                        return $crew->role === $progress->responsible_role;
                    });

                    if ($crewMember && $crewMember->user) {
                        $displayUser = [
                            'id' => $crewMember->user->id,
                            'name' => $crewMember->user->name,
                            'role' => $crewMember->role
                        ];
                    } else {
                        // Fallback to manually assigned user if no crew found (legacy support)
                        if ($progress->assignedUser) {
                            $displayUser = [
                                'id' => $progress->assignedUser->id,
                                'name' => $progress->assignedUser->name,
                                'role' => $progress->assignedUser->role
                            ];
                        }
                    }
                }

                return [
                    'step_number' => $progress->workflow_step,
                    'step_name' => $progress->step_name,
                    'responsible_role' => $progress->responsible_role,
                    'responsible_roles' => $progress->responsible_roles, // Array of roles
                    'status' => $progress->status,
                    'color' => WorkflowStep::getStatusColor($progress->status),
                    'assigned_user' => $displayUser,
                    'started_at' => $progress->started_at?->toIso8601String(),
                    'completed_at' => $progress->completed_at?->toIso8601String(),
                    'duration_hours' => $progress->duration,
                    'notes' => $progress->notes
                ];
            });
        }


        return [
            'episode' => [
                'id' => $episode->id,
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'program_name' => $episode->program->name
            ],
            'workflow' => [
                'total_steps' => WorkflowStep::getTotalSteps(),
                'completion_percentage' => $episode->workflow_completion,
                'current_step' => $episode->currentWorkflowStep()?->workflow_step,
                'steps' => $steps
            ]
        ];
    }

    /**
     * Get workflow history
     */
    public function getWorkflowHistory(int $episodeId): array
    {
        $episode = PrEpisode::with(['workflowProgress.assignedUser'])->findOrFail($episodeId);

        $history = $episode->workflowProgress
            ->filter(fn($p) => $p->started_at !== null)
            ->sortByDesc('updated_at')
            ->map(function ($progress) {
                return [
                    'step_number' => $progress->workflow_step,
                    'step_name' => $progress->step_name,
                    'status' => $progress->status,
                    'assigned_user' => $progress->assignedUser?->name,
                    'started_at' => $progress->started_at?->toIso8601String(),
                    'completed_at' => $progress->completed_at?->toIso8601String(),
                    'duration_hours' => $progress->duration,
                    'notes' => $progress->notes
                ];
            })
            ->values();

        return [
            'episode_id' => $episodeId,
            'history' => $history
        ];
    }

    /**
     * Check if user can access a step (based on role)
     */
    public function canUserAccessStep(User $user, int $stepNumber): bool
    {
        return WorkflowStep::canRoleAccessStep($user->role, $stepNumber);
    }

    /**
     * Update step notes
     */
    public function updateStepNotes(int $episodeId, int $stepNumber, string $notes): PrEpisodeWorkflowProgress
    {
        $progress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $stepNumber)
            ->firstOrFail();

        $progress->update(['notes' => $notes]);

        return $progress->fresh(['assignedUser', 'episode']);
    }

    /**
     * Auto-start next step (set to pending, not in_progress)
     * This is just to prepare the next step
     */
    protected function autoStartNextStep(int $episodeId, int $currentStep): void
    {
        $nextStepNumber = $currentStep + 1;

        if (!WorkflowStep::isValidStep($nextStepNumber)) {
            Log::info('Workflow completed for episode', ['episode_id' => $episodeId]);
            return;
        }

        $nextStep = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $nextStepNumber)
            ->first();

        if ($nextStep && $nextStep->status === WorkflowStep::STATUS_PENDING) {
            Log::info('Next workflow step ready', [
                'episode_id' => $episodeId,
                'next_step' => $nextStepNumber,
                'step_name' => $nextStep->step_name
            ]);
        }
    }

    /**
     * Reset a step (back to pending)
     * Only for Manager Program role
     */
    public function resetStep(int $episodeId, int $stepNumber): PrEpisodeWorkflowProgress
    {
        $progress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
            ->where('workflow_step', $stepNumber)
            ->firstOrFail();

        $progress->update([
            'status' => WorkflowStep::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'assigned_user_id' => null,
            'notes' => null
        ]);

        Log::info('Workflow step reset', [
            'episode_id' => $episodeId,
            'step_number' => $stepNumber
        ]);

        return $progress->fresh(['assignedUser', 'episode']);
    }
}
