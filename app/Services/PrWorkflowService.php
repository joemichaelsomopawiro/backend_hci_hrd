<?php

namespace App\Services;

use App\Models\PrEpisode;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\User;
use App\Constants\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrQualityControlWork;
use App\Models\PrManagerDistribusiQcWork;
use App\Models\PrBroadcastingWork;
use App\Models\PrActivityLog;
use App\Services\PrActivityLogService;
use App\Services\PrNotificationService;
use Carbon\Carbon;

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
            $episodeAirDate = \Carbon\Carbon::parse($episode->air_date);
            
            // Get program start date for steps 1 & 2
            $programStartDate = $episode->program ? \Carbon\Carbon::parse($episode->program->start_date) : $episodeAirDate;

            foreach ($steps as $stepNumber => $stepInfo) {
                // Use deadline_days_before from WorkflowStep constants (based on KPI spreadsheet)
                $daysBefore = $stepInfo['deadline_days_before'] ?? 7;

                // Steps 1 and 2 are program-level, so deadline is relative to Episode 1 (program start date)
                $referenceDate = in_array($stepNumber, [1, 2]) ? $programStartDate : $episodeAirDate;
                $deadlineAt = $referenceDate->copy()->subDays($daysBefore)->startOfDay();

                PrEpisodeWorkflowProgress::create([
                    'episode_id' => $episode->id,
                    'workflow_step' => $stepNumber,
                    'step_name' => $stepInfo['name'],
                    'responsible_role' => $stepInfo['role'],
                    'status' => WorkflowStep::STATUS_PENDING,
                    'deadline_at' => $deadlineAt
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
     * Get completion status for each role involved in a step
     */
    public function getRoleCompletions($episode, $stepNumber): array
    {
        $completions = [];

        if ($stepNumber == 1) {
            $completions['Manager Program'] = $episode->status !== 'draft';
        } elseif ($stepNumber == 2) {
            $completions['Producer'] = !empty($episode->accepted_at) || ($episode->program && $episode->program->read_by_producer);
        } elseif ($stepNumber == 3) {
            $creativeWork = PrCreativeWork::where('pr_episode_id', $episode->id)->first();
            $completions['Kreatif'] = $creativeWork && $creativeWork->status !== 'pending';
        } elseif ($stepNumber == 4) {
            $creativeWork = PrCreativeWork::where('pr_episode_id', $episode->id)->first();
            $completions['Producer'] = $creativeWork && $creativeWork->script_approved;
            $completions['Manager Program'] = $creativeWork && $creativeWork->budget_approved;
        } elseif ($stepNumber == 5) {
            $produksiWork = \App\Models\PrProduksiWork::where('pr_episode_id', $episode->id)->first();
            $promosiWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episode->id)->first();
            $equipmentLoans = \App\Models\EquipmentLoan::whereHas('produksiWorks', function($q) use ($episode) {
                $q->where('pr_episode_id', $episode->id);
            })->get();

            $completions['Produksi'] = $produksiWork && $produksiWork->status === 'completed';
            $completions['Promosi'] = $promosiWork && ($promosiWork->status === 'completed' || !empty($promosiWork->file_paths));
            
            // Art & Set completion: If loans exist, they must be at least 'active' (picked up) or further
            // If no loans, we count as complete (some episodes might not need extra equipment)
            $completions['Art & Set Properti'] = $equipmentLoans->isEmpty() || $equipmentLoans->every(function($loan) {
                return in_array($loan->status, ['active', 'return_requested', 'completed', 'returned']);
            });
        } elseif ($stepNumber == 6) {
            $editorWork = PrEditorWork::where('pr_episode_id', $episode->id)->first();
            $editorPromoWork = PrEditorPromosiWork::where('pr_episode_id', $episode->id)->first();
            $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episode->id)->first();

            $completions['Editor'] = $editorWork && in_array($editorWork->status, ['pending_qc', 'completed']);
            $completions['Editor Promosi'] = $editorPromoWork && in_array($editorPromoWork->status, ['pending_qc', 'completed']);
            $completions['Design Grafis'] = $designGrafisWork && in_array($designGrafisWork->status, ['pending_qc', 'completed']);
        } elseif ($stepNumber == 7) {
            $qcWork = PrManagerDistribusiQcWork::where('pr_episode_id', $episode->id)->first();
            $completions['Manager Distribusi'] = $qcWork && $qcWork->status === 'completed';
        } elseif ($stepNumber == 8) {
            $qcWork = PrQualityControlWork::where('pr_episode_id', $episode->id)->first();
            $completions['QC'] = $qcWork && $qcWork->status === 'completed';
        } elseif ($stepNumber == 9) {
            $broadcastingWork = PrBroadcastingWork::where('pr_episode_id', $episode->id)->first();
            $completions['Broadcasting'] = $broadcastingWork && in_array($broadcastingWork->status, ['completed', 'published']);
        } elseif ($stepNumber == 10) {
            $promotionWork = PrPromotionWork::where('pr_episode_id', $episode->id)->first();

            // Step 10 is only complete if sharing_proof tasks exist and episode is promoted
            $hasSharingTasks = $promotionWork && !empty($promotionWork->sharing_proof['share_konten_tasks']);
            $completions['Promosi'] = $hasSharingTasks || ($episode->status === 'promoted');
        }

        return $completions;
    }

    /**
     * Check and synchronize workflow step status based on role completions
     */
    public function syncStepProgress(int $episodeId, int $stepNumber): void
    {
        $episode = \App\Models\PrEpisode::findOrFail($episodeId);
        
        // SELF-HEALING: Ensure role work statuses are synced with QC results before checking progress
        $this->syncRoleWorkStatusFromQC($episodeId);

        $roleCompletions = $this->getRoleCompletions($episode, $stepNumber);

        if (empty($roleCompletions)) {
            return;
        }

        $allDone = true;
        foreach ($roleCompletions as $isDone) {
            if (!$isDone) {
                $allDone = false;
                break;
            }
        }

        if ($allDone) {
            // Auto-create/Sync next phase work records (even if step status was already completed)
            if ($stepNumber === 5) {
                $this->createStep6WorkRecords($episode);
            } elseif ($stepNumber === 7) {
                $this->createStep8WorkRecord($episode);
            }

            $progress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', $stepNumber)
                ->first();

            if ($progress && $progress->status !== WorkflowStep::STATUS_COMPLETED) {
                $progress->update([
                    'status' => WorkflowStep::STATUS_COMPLETED,
                    'completed_at' => now()
                ]);

                // Also update episode workflow_step if it's currently at this step
                if ($episode->workflow_step == $stepNumber) {
                    $episode->update(['workflow_step' => $stepNumber + 1]);
                }

                // NEW SEPARATE TRIGGERS FOR STEP 7 AND STEP 8
                // Step 7 (Distribusi QC): Triggers when Main Video Editor is done
                if ($roleCompletions['Editor'] ?? false) {
                    $this->createStep7WorkRecord($episode);
                }

                // Step 8 (Standard QC): Triggers when BOTH Promo and Graphic Design are done
                if (($roleCompletions['Editor Promosi'] ?? false) && ($roleCompletions['Design Grafis'] ?? false)) {
                    $this->createStep8WorkRecord($episode);
                }

                // Log overall completion if all are done
                if ($allDone) {
                    app(\App\Services\PrActivityLogService::class)->logEpisodeActivity(
                        $episode,
                        'complete_step',
                        "Completed workflow step {$stepNumber}: {$progress->step_name} (All roles finished)",
                        ['step' => $stepNumber, 'status' => 'completed']
                    );
                }

                // Log activity
                app(\App\Services\PrActivityLogService::class)->logEpisodeActivity(
                    $episode,
                    'complete_step',
                    "Completed workflow step {$stepNumber}: {$progress->step_name} (All roles finished)",
                    ['step' => $stepNumber, 'status' => 'completed']
                );

                // Trigger Notifications for the NEXT step
                $nextStep = $stepNumber + 1;
                if ($nextStep <= 10) {
                    app(PrNotificationService::class)->notifyWorkflowStepReady($episodeId, $nextStep);
                }
            }
        } else {
            // SPECIAL LOGIC: Partial completion triggers for Step 6
            if ($stepNumber === 6) {
                // Also wake up any Editor Promosi work that was waiting for the Editor to finish
                if ($roleCompletions['Editor'] ?? false) {
                    $promoEditorWork = PrEditorPromosiWork::where('pr_episode_id', $episodeId)
                        ->where('status', 'waiting_editor')
                        ->first();
                    if ($promoEditorWork) {
                        // SELF-HEALING: If it's missing the editor_work link, but we know Editor is done, find it
                        if (!$promoEditorWork->pr_editor_work_id) {
                            $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)
                                ->where('work_type', 'main_episode')
                                ->first();
                            if ($editorWork) {
                                $promoEditorWork->pr_editor_work_id = $editorWork->id;
                            }
                        }
                        
                        $promoEditorWork->update(['status' => 'pending']);
                        Log::info("Transitioned PrEditorPromosiWork for Episode [{$episodeId}] from waiting_editor to pending");
                    }
                }
            }
        }
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
            // Move episode to step 5
            $episode = \App\Models\PrEpisode::find($episodeId);
            if ($episode && $episode->workflow_step < 5) {
                $episode->update(['workflow_step' => 5]);
            }
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
                'work_type' => 'bts_video', // Default work type
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
     * Create Step 6 work records (Editor, Editor Promosi, Design Grafis)
     */
    protected function createStep6WorkRecords(PrEpisode $episode): void
    {
        try {
            $produksiWork = PrProduksiWork::where('pr_episode_id', $episode->id)->first();
            $promosiWork = PrPromotionWork::where('pr_episode_id', $episode->id)->first();

            if (!$produksiWork || !$promosiWork) {
                Log::warning('Cannot create Step 6 records: Step 5 works not found', ['episode_id' => $episode->id]);
                return;
            }

            // 1. Create Editor work
            $editorWork = PrEditorWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $produksiWork->id,
                    'work_type' => 'main_episode',
                    'status' => 'pending',
                    'files_complete' => false
                ]
            );

            // 2. Create Editor Promosi work
            PrEditorPromosiWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_editor_work_id' => $editorWork->id,
                    'pr_promotion_work_id' => $promosiWork->id,
                    'status' => 'pending'
                ]
            );

            // 3. Create Design Grafis work
            PrDesignGrafisWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $produksiWork->id,
                    'pr_promotion_work_id' => $promosiWork->id,
                    'status' => 'pending'
                ]
            );

            Log::info('Step 6 work records created successfully', ['episode_id' => $episode->id]);
        } catch (\Exception $e) {
            Log::error('Failed to create Step 6 work records', [
                'episode_id' => $episode->id,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create Manager Distribusi QC (Step 7) work record
     */
    protected function createStep7WorkRecord(PrEpisode $episode): void
    {
        try {
            // Find Editor's work (main episode)
            // Be flexible: try main_episode first, then fallback to first available if work_type is null
            $editorWork = PrEditorWork::where('pr_episode_id', $episode->id)
                ->where('work_type', 'main_episode')
                ->first() ?? PrEditorWork::where('pr_episode_id', $episode->id)->first();

            if (!$editorWork) {
                Log::warning('Cannot create Step 7 records: Editor work not found', ['episode_id' => $episode->id]);
                return;
            }

            // Create or Update QC Work
            $qcWork = PrManagerDistribusiQcWork::where('pr_episode_id', $episode->id)->first();
            
            if (!$qcWork) {
                PrManagerDistribusiQcWork::create([
                    'pr_episode_id' => $episode->id,
                    'status' => 'pending',
                    'recieved_at' => now(),
                ]);
            } else {
                // If it already exists, make sure it's back to pending for review
                // unless it was already completed (safety check)
                if ($qcWork->status !== 'completed') {
                    $qcWork->update([
                        'status' => 'pending',
                        'recieved_at' => now(), // Refresh received time
                    ]);
                }
            }

            Log::info('Step 7 work record created successfully', ['episode_id' => $episode->id]);
        } catch (\Exception $e) {
            Log::error('Failed to create Step 7 work record', [
                'episode_id' => $episode->id,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Auto-create Quality Control (Step 8) work record
     */
    protected function createStep8WorkRecord(PrEpisode $episode): void
    {
        try {
            $editorWork = PrEditorWork::where('pr_episode_id', $episode->id)->first();
            $editorPromoWork = PrEditorPromosiWork::where('pr_episode_id', $episode->id)->first();
            $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episode->id)->first();

            // We follow the user's logic: QC Final appears if all 3 roles are finished
            // In getRoleCompletions, we defined "finished" as pending_qc or completed.

            $qcWork = PrQualityControlWork::where('pr_episode_id', $episode->id)->first();
            $isNew = false;

            if (!$qcWork) {
                $qcWork = PrQualityControlWork::create([
                    'pr_episode_id' => $episode->id,
                    'status' => 'pending'
                ]);
                $isNew = true;
            }

            $updateData = [];

            // Logic for Re-submission: 
            // If QC already exists and was in a non-pending state (like rejected or in_progress with revisions),
            // and now everyone submitted again, we should flag it.
            if (!$isNew) {
                $checklist = $qcWork->qc_checklist ?? [];
                $hasRevisedItems = false;

                foreach ($checklist as $key => $data) {
                    if (isset($data['status']) && $data['status'] === 'revision') {
                        $checklist[$key]['status'] = 'revised';
                        $checklist[$key]['revised_at'] = now()->toIso8601String();
                        $hasRevisedItems = true;
                    }
                }

                if ($hasRevisedItems) {
                    $updateData['qc_checklist'] = $checklist;
                    // Reset status to pending so it's fresh for QC
                    $updateData['status'] = 'pending';

                    Log::info('QC Record flagged as revised', ['episode_id' => $episode->id]);
                }
            }

            if ($editorPromoWork) {
                $updateData['editor_promosi_file_locations'] = [
                    'bts_video' => $editorPromoWork->bts_video_link,
                    'tv_ad' => $editorPromoWork->tv_ad_link,
                    'ig_highlight' => $editorPromoWork->ig_highlight_link,
                    'tv_highlight' => $editorPromoWork->tv_highlight_link,
                    'fb_highlight' => $editorPromoWork->fb_highlight_link,
                ];
            }

            if ($designGrafisWork) {
                $updateData['design_grafis_file_locations'] = [
                    'youtube_thumbnail' => $designGrafisWork->youtube_thumbnail_link,
                    'bts_thumbnail' => $designGrafisWork->bts_thumbnail_link,
                    'episode_poster' => $designGrafisWork->episode_poster_link,
                ];
            }

            if (!empty($updateData)) {
                $qcWork->update($updateData);
            }

            Log::info('Step 8 QC record created/synchronized successfully', ['episode_id' => $episode->id]);
        } catch (\Exception $e) {
            Log::error('Failed to create Step 8 QC record', [
                'episode_id' => $episode->id,
                'message' => $e->getMessage()
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

        // Fetch all activity logs for this episode to attach as history
        $activityLogs = \App\Models\PrActivityLog::with('user')
            ->where('episode_id', $episodeId)
            ->orderBy('created_at', 'asc')
            ->get();

        $steps = $episode->workflowProgress->map(function ($progress) use ($episode, $activityLogs) {
            // Get per-role completion status for multi-role steps
            $roleCompletions = $this->getRoleCompletions($episode, $progress->workflow_step);

            // Check for partial completion logic for step 5 and 6
            $displayStatus = $progress->status;

            // OVERRIDE: If not all roles are completed for step 5 and 6, it cannot be 'completed'
            if (in_array($progress->workflow_step, [5, 6])) {
                $allRolesDone = true;
                if (empty($roleCompletions)) {
                    $allRolesDone = false;
                } else {
                    foreach ($roleCompletions as $role => $isDone) {
                        if (!$isDone) {
                            $allRolesDone = false;
                            break;
                        }
                    }
                }

                if ($progress->status === 'completed' && !$allRolesDone) {
                    $displayStatus = 'in_progress';
                }
            }

            if ($displayStatus === 'pending' || $displayStatus === 'in_progress') {
                if ($progress->workflow_step == 4) {
                    $creativeWork = PrCreativeWork::where('pr_episode_id', $episode->id)->first();
                    if ($creativeWork && ($creativeWork->script_approved || $creativeWork->budget_approved)) {
                        $displayStatus = 'in_progress';
                    }
                } elseif ($progress->workflow_step == 5) {
                    $produksiCompleted = PrProduksiWork::where('pr_episode_id', $episode->id)
                        ->where('status', 'completed')->exists();
                    $promosiCompleted = PrPromotionWork::where('pr_episode_id', $episode->id)
                        ->where('status', 'completed')->exists();

                    if ($produksiCompleted || $promosiCompleted) {
                        $displayStatus = 'in_progress';
                    }
                } elseif ($progress->workflow_step == 6) {
                    $editorWork = PrEditorWork::where('pr_episode_id', $episode->id)->first();
                    $promoWork = PrPromotionWork::where('pr_episode_id', $episode->id)->first();
                    $editorPromoWork = PrEditorPromosiWork::where('pr_episode_id', $episode->id)->first();
                    $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episode->id)->first();

                    $someComplete = false;
                    foreach ([$editorWork, $editorPromoWork, $designGrafisWork] as $work) {
                        if ($work && in_array($work->status, ['pending_qc', 'completed'])) {
                            $someComplete = true;
                            break;
                        }
                    }
                    if ($promoWork && $promoWork->status === 'completed') {
                        $someComplete = true;
                    }

                    if ($someComplete) {
                        $displayStatus = 'in_progress';
                    }
                }
            }

            // Determine the "assigned user" to display based on Role Logic
            $displayUser = null;

            // Get per-role completion status for multi-role steps (5 and 6)
            $roleCompletions = $this->getRoleCompletions($episode, $progress->workflow_step);

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

            // Filter activity logs relevant to this step
            $stepActivities = $activityLogs->filter(function ($log) use ($progress) {
                // Determine if log belongs to this step
                if (isset($log->changes['step']) && $log->changes['step'] == $progress->workflow_step) {
                    return true;
                }

                // Fallback: Check if description explicitly mentions the step number
                return str_contains(strtolower($log->description), 'step ' . $progress->workflow_step);
            })->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name
                    ] : null,
                    'created_at' => $log->created_at->toIso8601String()
                ];
            })->values()->toArray();

            // Calculate is_late
            $isLate = false;
            if ($progress->completed_at && $progress->deadline_at) {
                $isLate = $progress->completed_at->greaterThan($progress->deadline_at);
            } elseif (!$progress->completed_at && $progress->deadline_at) {
                $isLate = now()->greaterThan($progress->deadline_at);
            }

            return [
                'step_number' => $progress->workflow_step,
                'step_name' => $progress->step_name,
                'responsible_role' => $progress->responsible_role,
                'responsible_roles' => $progress->responsible_roles, // Array of roles
                'status' => $displayStatus,
                'color' => WorkflowStep::getStatusColor($displayStatus),
                'assigned_user' => $displayUser,
                'role_completions' => $roleCompletions,
                'started_at' => $progress->started_at?->toIso8601String(),
                'completed_at' => $progress->completed_at?->toIso8601String(),
                'deadline_at' => $progress->deadline_at?->toIso8601String(),
                'is_late' => $isLate,
                'duration_hours' => $progress->duration,
                'notes' => $progress->notes,
                'activities' => $stepActivities
            ];
        });

        // ==================== SELF-HEALING LOGIC ====================
        // This ensures the workflow stays in sync even if direct updates were missed
        $needRefresh = false;

        // SELF-HEALING: Check for Step 5 (Pinjam Alat dan Syuting) completion
        // Criteria: Both ProductionWork and PromotionWork are 'completed'
        $step5 = $episode->workflowProgress->firstWhere('workflow_step', 5);
        if ($step5 && $step5->status !== 'completed') {
            try {
                $productionWork = PrProduksiWork::where('pr_episode_id', $episodeId)->first();
                $promotionWork = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                if (
                    $productionWork && $productionWork->status === 'completed' &&
                    $promotionWork && $promotionWork->status === 'completed'
                ) {
                    $step5->update([
                        'status' => 'completed',
                        'completed_at' => $productionWork->completed_at ?? $promotionWork->updated_at ?? now(),
                        'notes' => 'Auto-completed: Production and Promotion works are both finished'
                    ]);
                    $needRefresh = true;
                }
            } catch (\Exception $e) {
                Log::error('Error checking Step 5 completion: ' . $e->getMessage());
            }
        }

        // SELF-HEALING: Check for Step 6 (Edit Konten) completion
        // Criteria:
        // 1. Editor Work: 'pending_qc' or 'completed'
        // 2. Promotion Work: 'completed'
        // 3. Editor Promosi Work: 'pending_qc' or 'completed'
        $step6 = $episode->workflowProgress->firstWhere('workflow_step', 6);
        if ($step6 && $step6->status !== 'completed') {
            try {
                $editorWork = PrEditorWork::where('pr_episode_id', $episodeId)->first();
                $promoWork = PrPromotionWork::where('pr_episode_id', $episodeId)->first();
                $editorPromoWork = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
                $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

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
                    $steps = $episode->workflowProgress->map(function ($progress) use ($episode, $activityLogs) {
                        // Check for partial completion logic for step 5 and 6
                        $displayStatus = $progress->status;
                        if ($progress->status === 'pending' || $progress->status === 'in_progress') {
                            if ($progress->workflow_step == 5) {
                                $produksiCompleted = PrProduksiWork::where('pr_episode_id', $episode->id)
                                    ->where('status', 'completed')->exists();
                                $promosiCompleted = PrPromotionWork::where('pr_episode_id', $episode->id)
                                    ->where('status', 'completed')->exists();

                                if ($produksiCompleted || $promosiCompleted) {
                                    $displayStatus = 'in_progress';
                                }
                            } elseif ($progress->workflow_step == 6) {
                                $editorWork = PrEditorWork::where('pr_episode_id', $episode->id)->first();
                                $promoWork = PrPromotionWork::where('pr_episode_id', $episode->id)->first();
                                $editorPromoWork = PrEditorPromosiWork::where('pr_episode_id', $episode->id)->first();
                                $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $episode->id)->first();

                                $someComplete = false;
                                foreach ([$editorWork, $editorPromoWork, $designGrafisWork] as $work) {
                                    if ($work && in_array($work->status, ['pending_qc', 'completed'])) {
                                        $someComplete = true;
                                        break;
                                    }
                                }
                                if ($promoWork && $promoWork->status === 'completed') {
                                    $someComplete = true;
                                }

                                if ($someComplete) {
                                    $displayStatus = 'in_progress';
                                }
                            }
                        }

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

                        // Filter activity logs relevant to this step
                        $stepActivities = $activityLogs->filter(function ($log) use ($progress) {
                            if (isset($log->changes['step']) && $log->changes['step'] == $progress->workflow_step) {
                                return true;
                            }
                            return str_contains(strtolower($log->description), 'step ' . $progress->workflow_step);
                        })->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'action' => $log->action,
                                'description' => $log->description,
                                'user' => $log->user ? [
                                    'id' => $log->user->id,
                                    'name' => $log->user->name
                                ] : null,
                                'created_at' => $log->created_at->toIso8601String()
                            ];
                        })->values()->toArray();

                        return [
                            'step_number' => $progress->workflow_step,
                            'step_name' => $progress->step_name,
                            'responsible_role' => $progress->responsible_role,
                            'responsible_roles' => $progress->responsible_roles, // Array of roles
                            'status' => $displayStatus,
                            'color' => WorkflowStep::getStatusColor($displayStatus),
                            'assigned_user' => $displayUser,
                            'started_at' => $progress->started_at?->toIso8601String(),
                            'completed_at' => $progress->completed_at?->toIso8601String(),
                            'duration_hours' => $progress->duration,
                            'notes' => $progress->notes,
                            'activities' => $stepActivities
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
                $qcManagerWork = PrManagerDistribusiQcWork::where('pr_episode_id', $episodeId)->first();
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
                $qcFinalWork = PrQualityControlWork::where('pr_episode_id', $episodeId)->first();
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
            $creativeWork = PrCreativeWork::where('pr_episode_id', $episodeId)->first();
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

        // CASCADING COMPLETION: If Step N is completed, all steps 1 to N-1 MUST be completed
        // This prevents the "missing checkmarks" UI issue
        $maxCompletedStep = 0;
        foreach ($episode->workflowProgress as $p) {
            if ($p->status === 'completed') {
                $maxCompletedStep = max($maxCompletedStep, $p->workflow_step);
            }
        }

        if ($maxCompletedStep > 1) {
            foreach ($episode->workflowProgress as $p) {
                if ($p->workflow_step < $maxCompletedStep && $p->status !== 'completed') {
                    $p->update([
                        'status' => 'completed',
                        'completed_at' => $p->updated_at ?: now(),
                        'notes' => 'Auto-completed via cascading logic (Step ' . $maxCompletedStep . ' is done)'
                    ]);
                    $needRefresh = true;
                }
            }
        }

        if ($needRefresh) {
            // Refresh the steps collection to reflect all changes
            $episode->load('workflowProgress');
            // Re-map steps
            $steps = $episode->workflowProgress->map(function ($progress) use ($episode, $activityLogs) {
                // Determine the "assigned user" to display based on Role Logic
                $displayUser = null;

                $roleCompletions = $this->getRoleCompletions($episode, $progress->workflow_step);

                if ($progress->responsible_role === 'Program Manager') {
                    $displayUser = [
                        'id' => $episode->program->managerProgram->id,
                        'name' => $episode->program->managerProgram->name,
                        'role' => 'Program Manager'
                    ];
                } else {
                    $crewMember = $episode->program->crews->first(function ($crew) use ($progress) {
                        return $crew->role === $progress->responsible_role;
                    });

                    if ($crewMember && $crewMember->user) {
                        $displayUser = [
                            'id' => $crewMember->user->id,
                            'name' => $crewMember->user->name,
                            'role' => $crewMember->role
                        ];
                    } else if ($progress->assignedUser) {
                        $displayUser = [
                            'id' => $progress->assignedUser->id,
                            'name' => $progress->assignedUser->name,
                            'role' => $progress->assignedUser->role
                        ];
                    }
                }

                $stepActivities = $activityLogs->filter(function ($log) use ($progress) {
                    if (isset($log->changes['step']) && $log->changes['step'] == $progress->workflow_step) {
                        return true;
                    }
                    return str_contains(strtolower($log->description), 'step ' . $progress->workflow_step);
                })->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'description' => $log->description,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name
                        ] : null,
                        'created_at' => $log->created_at->toIso8601String()
                    ];
                })->values()->toArray();

                return [
                    'step_number' => $progress->workflow_step,
                    'step_name' => $progress->step_name,
                    'responsible_role' => $progress->responsible_role,
                    'responsible_roles' => $progress->responsible_roles,
                    'status' => $progress->status,
                    'color' => WorkflowStep::getStatusColor($progress->status),
                    'assigned_user' => $displayUser,
                    'role_completions' => $roleCompletions,
                    'started_at' => $progress->started_at?->toIso8601String(),
                    'completed_at' => $progress->completed_at?->toIso8601String(),
                    'duration_hours' => $progress->duration,
                    'notes' => $progress->notes,
                    'activities' => $stepActivities
                ];
            });
        }


        // Fetch shared assets if Step 10 is reached/completed
        $sharedAssets = null;
        $step10 = $episode->workflowProgress->firstWhere('workflow_step', 10);

        if ($step10 && $step10->status === 'completed') {
            try {
                $broadcasting = PrBroadcastingWork::where('pr_episode_id', $episodeId)->first();
                $promotion = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                $sharedAssets = [
                    'youtube_url' => $broadcasting ? $broadcasting->youtube_url : null,
                    'sharing_proof' => $promotion ? $promotion->sharing_proof : null,
                ];
            } catch (\Exception $e) {
                Log::error('Error fetching shared assets for workflow: ' . $e->getMessage());
            }
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
                'steps' => $steps,
                'shared_assets' => $sharedAssets // Added shared_assets
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

            // Notify the role assignees that this step is ready for them
            try {
                app(\App\Services\PrNotificationService::class)->notifyWorkflowStepReady($episodeId, $nextStepNumber);
            } catch (\Exception $e) {
                Log::error('Failed to send workflow step ready notification', [
                    'episode_id' => $episodeId,
                    'step_number' => $nextStepNumber,
                    'error' => $e->getMessage()
                ]);
            }
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

    /**
     * SELF-HEALING: Sync role work status from QC results
     * Ensures that if QC is finished, the underlying work records are also marked as completed
     */
    public function syncRoleWorkStatusFromQC(int $episodeId): void
    {
        try {
            // 1. Sync from Step 7 (Distribution Manager QC) -> Main Video Editor
            $distribusiQc = \App\Models\PrManagerDistribusiQcWork::where('pr_episode_id', $episodeId)->first();
            if ($distribusiQc && $distribusiQc->status === 'completed') {
                \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)
                    ->where('work_type', 'main_episode')
                    ->where('status', '!=', 'completed')
                    ->update(['status' => 'completed']);
            }

            // 2. Sync from Step 8 (Standard QC) -> Promo & Graphic Design
            $standardQc = \App\Models\PrQualityControlWork::where('pr_episode_id', $episodeId)->first();
            if ($standardQc && $standardQc->status === 'completed') {
                \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)
                    ->where('status', '!=', 'completed')
                    ->update(['status' => 'completed']);
                
                \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episodeId)
                    ->where('status', '!=', 'completed')
                    ->update(['status' => 'completed']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync role work status from QC', [
                'episode_id' => $episodeId,
                'message' => $e->getMessage()
            ]);
        }
    }
}
