<?php

namespace App\Services;

use App\Models\MusicSubmission;
use App\Models\CreativeWork;
use App\Models\Budget;
use App\Models\BudgetApproval;
use App\Models\MusicSchedule;
use App\Models\ProductionTeamAssignment;
use App\Models\ProductionTeamMember;
use App\Models\MusicWorkflowHistory;
use App\Models\MusicWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class CreativeWorkflowService
{
    /**
     * Submit creative work (script, storyboard, budget, schedules)
     * Called by Kreatif role
     */
    public function submitCreativeWork($submissionId, $data, $files = [])
    {
        try {
            DB::beginTransaction();

            $submission = MusicSubmission::findOrFail($submissionId);

            // 1. Create or Update Creative Work (script & storyboard)
            $creativeWork = CreativeWork::updateOrCreate(
                ['music_submission_id' => $submissionId],
                [
                    'created_by' => auth()->id(),
                    'script_content' => $data['script_content'] ?? null,
                    'creative_notes' => $data['creative_notes'] ?? null,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );

            // Handle storyboard file upload
            if (isset($files['storyboard_file'])) {
                $file = $files['storyboard_file'];
                $path = $file->store('music/storyboards', 'public');
                
                $creativeWork->update([
                    'storyboard_file_path' => $path,
                    'storyboard_file_name' => $file->getClientOriginalName(),
                    'storyboard_file_size' => $file->getSize(),
                ]);
            }

            // 2. Create or Update Budget
            $budget = Budget::updateOrCreate(
                ['music_submission_id' => $submissionId],
                [
                    'created_by' => auth()->id(),
                    'talent_budget' => $data['talent_budget'] ?? 0,
                    'production_budget' => $data['production_budget'] ?? 0,
                    'other_budget' => $data['other_budget'] ?? 0,
                    'talent_budget_notes' => $data['talent_budget_notes'] ?? null,
                    'production_budget_notes' => $data['production_budget_notes'] ?? null,
                    'other_budget_notes' => $data['other_budget_notes'] ?? null,
                    'budget_notes' => $data['budget_notes'] ?? null,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );

            // Auto-calculate total
            $budget->calculateTotal();
            $budget->save();

            // 3. Create Recording Schedule
            if (isset($data['recording_datetime'])) {
                MusicSchedule::updateOrCreate(
                    [
                        'music_submission_id' => $submissionId,
                        'schedule_type' => 'recording',
                    ],
                    [
                        'created_by' => auth()->id(),
                        'scheduled_datetime' => $data['recording_datetime'],
                        'location' => $data['recording_location'] ?? 'Studio',
                        'location_address' => $data['recording_location_address'] ?? null,
                        'schedule_notes' => $data['recording_notes'] ?? null,
                        'status' => 'scheduled',
                    ]
                );
            }

            // 4. Create Shooting Schedule
            if (isset($data['shooting_datetime'])) {
                MusicSchedule::updateOrCreate(
                    [
                        'music_submission_id' => $submissionId,
                        'schedule_type' => 'shooting',
                    ],
                    [
                        'created_by' => auth()->id(),
                        'scheduled_datetime' => $data['shooting_datetime'],
                        'location' => $data['shooting_location'] ?? 'Location',
                        'location_address' => $data['shooting_location_address'] ?? null,
                        'schedule_notes' => $data['shooting_notes'] ?? null,
                        'status' => 'scheduled',
                    ]
                );
            }

            // 5. Update submission status
            $submission->update([
                'current_state' => 'creative_review', // Waiting for Producer review
            ]);

            // 6. Create workflow history
            MusicWorkflowHistory::create([
                'submission_id' => $submissionId,
                'from_state' => $submission->current_state,
                'to_state' => 'creative_review',
                'action' => 'creative_submitted',
                'action_by_user_id' => auth()->id(),
                'action_notes' => 'Creative work submitted for review',
            ]);

            // 7. Notify Producer
            $this->notifyProducer($submission, 'Creative work submitted and ready for review');

            DB::commit();

            return [
                'success' => true,
                'message' => 'Creative work submitted successfully',
                'data' => [
                    'creative_work' => $creativeWork->fresh(),
                    'budget' => $budget->fresh(),
                    'schedules' => $submission->schedules,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to submit creative work: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Producer reviews creative work
     * Can approve/reject script, storyboard, and budget separately
     */
    public function reviewCreativeWork($submissionId, $data)
    {
        try {
            DB::beginTransaction();

            $submission = MusicSubmission::findOrFail($submissionId);
            $creativeWork = $submission->creativeWork;
            $budget = $submission->budget;

            if (!$creativeWork || !$budget) {
                throw new Exception('Creative work or budget not found');
            }

            // Update creative work review
            $creativeWork->update([
                'script_approved' => $data['script_approved'] ?? false,
                'storyboard_approved' => $data['storyboard_approved'] ?? false,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                'status' => ($data['script_approved'] && $data['storyboard_approved']) ? 'approved' : 'revision',
            ]);

            // Update budget review (Producer can edit budget)
            if (isset($data['budget_edits'])) {
                $budget->update([
                    'talent_budget' => $data['budget_edits']['talent_budget'] ?? $budget->talent_budget,
                    'production_budget' => $data['budget_edits']['production_budget'] ?? $budget->production_budget,
                    'other_budget' => $data['budget_edits']['other_budget'] ?? $budget->other_budget,
                ]);
                $budget->calculateTotal();
                $budget->save();
            }

            $budget->update([
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_notes' => $data['budget_review_notes'] ?? null,
            ]);

            // Check if special budget approval is needed
            $requiresSpecialApproval = $data['request_special_approval'] ?? false;
            
            if ($requiresSpecialApproval) {
                $budget->update([
                    'requires_special_approval' => true,
                    'status' => 'pending_special_approval',
                ]);

                // Create budget approval request
                $this->requestSpecialBudgetApproval($submission, $budget, $data['special_approval_reason'] ?? null);
            } else {
                $budget->update([
                    'status' => $data['budget_approved'] ?? false ? 'approved' : 'revision',
                ]);
            }

            // Determine next action
            $allApproved = $creativeWork->script_approved && 
                          $creativeWork->storyboard_approved && 
                          ($budget->status === 'approved' || $budget->status === 'pending_special_approval');

            if ($allApproved) {
                if ($requiresSpecialApproval) {
                    // Wait for Manager Program approval
                    $submission->update(['current_state' => 'manager_budget_approval']);
                    $this->notifyManagerProgram($submission, 'Special budget approval needed');
                } else {
                    // Proceed to next phase
                    $submission->update(['current_state' => 'production_preparation']);
                }
            } else {
                // Send back to Kreatif for revision
                $submission->update(['current_state' => 'creative_revision']);
                $this->notifyCreative($submission, 'Creative work needs revision');
            }

            // Create workflow history
            MusicWorkflowHistory::create([
                'submission_id' => $submissionId,
                'from_state' => 'creative_review',
                'to_state' => $submission->current_state,
                'action' => 'creative_reviewed',
                'action_by_user_id' => auth()->id(),
                'action_notes' => $data['review_notes'] ?? 'Creative work reviewed',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Creative work reviewed successfully',
                'data' => [
                    'creative_work' => $creativeWork->fresh(),
                    'budget' => $budget->fresh(),
                    'next_state' => $submission->current_state,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to review creative work: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Request special budget approval from Manager Program
     */
    public function requestSpecialBudgetApproval($submission, $budget, $reason = null)
    {
        $budgetApproval = BudgetApproval::create([
            'budget_id' => $budget->id,
            'music_submission_id' => $submission->id,
            'requested_by' => auth()->id(),
            'requested_amount' => $budget->total_budget,
            'request_reason' => $reason,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return $budgetApproval;
    }

    /**
     * Manager Program approves/rejects special budget
     */
    public function processSpecialBudgetApproval($approvalId, $data)
    {
        try {
            DB::beginTransaction();

            $approval = BudgetApproval::findOrFail($approvalId);
            $budget = $approval->budget;
            $submission = $approval->musicSubmission;

            $isApproved = $data['decision'] === 'approved';
            $approvedAmount = $data['approved_amount'] ?? $approval->requested_amount;

            $approval->update([
                'approved_by' => auth()->id(),
                'approved_amount' => $approvedAmount,
                'approval_notes' => $data['approval_notes'] ?? null,
                'status' => $isApproved ? ($approvedAmount != $approval->requested_amount ? 'revised' : 'approved') : 'rejected',
                'approved_at' => $isApproved ? now() : null,
                'rejected_at' => !$isApproved ? now() : null,
            ]);

            if ($isApproved) {
                // Update budget dengan approved amount (if revised)
                if ($approvedAmount != $budget->total_budget) {
                    // Proportionally adjust budget components
                    $ratio = $approvedAmount / $budget->total_budget;
                    $budget->update([
                        'talent_budget' => $budget->talent_budget * $ratio,
                        'production_budget' => $budget->production_budget * $ratio,
                        'other_budget' => $budget->other_budget * $ratio,
                    ]);
                    $budget->calculateTotal();
                    $budget->save();
                }

                $budget->update(['status' => 'special_approved']);
                $submission->update(['current_state' => 'production_preparation']);
                
                $this->notifyProducer($submission, 'Special budget approved, proceed to production');
            } else {
                $budget->update(['status' => 'rejected']);
                $submission->update(['current_state' => 'creative_revision']);
                
                $this->notifyProducer($submission, 'Special budget rejected, please revise');
            }

            // Create workflow history
            MusicWorkflowHistory::create([
                'submission_id' => $submission->id,
                'from_state' => 'manager_budget_approval',
                'to_state' => $submission->current_state,
                'action' => $isApproved ? 'budget_approved' : 'budget_rejected',
                'action_by_user_id' => auth()->id(),
                'action_notes' => $data['approval_notes'] ?? null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => $isApproved ? 'Budget approved successfully' : 'Budget rejected',
                'data' => [
                    'approval' => $approval->fresh(),
                    'budget' => $budget->fresh(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to process budget approval: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Assign production teams (shooting, setting, recording)
     * Called by Producer
     */
    public function assignProductionTeams($submissionId, $data)
    {
        try {
            DB::beginTransaction();

            $submission = MusicSubmission::findOrFail($submissionId);

            $assignments = [];

            // Assign shooting team
            if (isset($data['shooting_team_ids']) && count($data['shooting_team_ids']) > 0) {
                $shootingTeam = $this->createTeamAssignment(
                    $submissionId,
                    'shooting',
                    $data['shooting_team_ids'],
                    $data['shooting_schedule_id'] ?? null,
                    $data['shooting_team_notes'] ?? null
                );
                $assignments['shooting_team'] = $shootingTeam;
            }

            // Assign setting team
            if (isset($data['setting_team_ids']) && count($data['setting_team_ids']) > 0) {
                $settingTeam = $this->createTeamAssignment(
                    $submissionId,
                    'setting',
                    $data['setting_team_ids'],
                    $data['shooting_schedule_id'] ?? null, // Same schedule as shooting
                    $data['setting_team_notes'] ?? null
                );
                $assignments['setting_team'] = $settingTeam;
            }

            // Assign recording team
            if (isset($data['recording_team_ids']) && count($data['recording_team_ids']) > 0) {
                $recordingTeam = $this->createTeamAssignment(
                    $submissionId,
                    'recording',
                    $data['recording_team_ids'],
                    $data['recording_schedule_id'] ?? null,
                    $data['recording_team_notes'] ?? null
                );
                $assignments['recording_team'] = $recordingTeam;
            }

            // Create workflow history
            MusicWorkflowHistory::create([
                'submission_id' => $submissionId,
                'from_state' => $submission->current_state,
                'to_state' => $submission->current_state, // State doesn't change
                'action' => 'teams_assigned',
                'action_by_user_id' => auth()->id(),
                'action_notes' => 'Production teams assigned',
            ]);

            // Notify team members
            foreach ($assignments as $team) {
                foreach ($team->members as $member) {
                    MusicWorkflowNotification::create([
                        'submission_id' => $submissionId,
                        'user_id' => $member->user_id,
                        'notification_type' => 'team_assignment',
                        'title' => 'You have been assigned to a production team',
                        'message' => "You have been assigned as {$member->getRoleLabel()} in {$team->getTeamTypeLabel()}",
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Production teams assigned successfully',
                'data' => $assignments,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to assign teams: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Helper: Create team assignment with members
     */
    private function createTeamAssignment($submissionId, $teamType, $memberIds, $scheduleId = null, $notes = null)
    {
        $assignment = ProductionTeamAssignment::create([
            'music_submission_id' => $submissionId,
            'schedule_id' => $scheduleId,
            'assigned_by' => auth()->id(),
            'team_type' => $teamType,
            'team_name' => ucfirst($teamType) . ' Team',
            'team_notes' => $notes,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        // Add team members
        foreach ($memberIds as $index => $userId) {
            ProductionTeamMember::create([
                'assignment_id' => $assignment->id,
                'user_id' => $userId,
                'role' => $index === 0 ? 'leader' : 'crew', // First member is leader
                'status' => 'assigned',
            ]);
        }

        return $assignment->load('members.user');
    }

    /**
     * Cancel or reschedule a schedule
     */
    public function manageSchedule($scheduleId, $action, $data)
    {
        try {
            DB::beginTransaction();

            $schedule = MusicSchedule::findOrFail($scheduleId);

            if ($action === 'cancel') {
                $schedule->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $data['reason'] ?? null,
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now(),
                ]);
                $message = 'Schedule cancelled successfully';
            } elseif ($action === 'reschedule') {
                $schedule->update([
                    'rescheduled_datetime' => $data['new_datetime'],
                    'reschedule_reason' => $data['reason'] ?? null,
                    'rescheduled_by' => auth()->id(),
                    'rescheduled_at' => now(),
                    'status' => 'rescheduled',
                ]);
                $message = 'Schedule rescheduled successfully';
            }

            // Create workflow history
            MusicWorkflowHistory::create([
                'submission_id' => $schedule->music_submission_id,
                'from_state' => $schedule->musicSubmission->current_state,
                'to_state' => $schedule->musicSubmission->current_state,
                'action' => "schedule_{$action}",
                'action_by_user_id' => auth()->id(),
                'action_notes' => $data['reason'] ?? "Schedule {$action}",
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => $message,
                'data' => $schedule->fresh(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => "Failed to {$action} schedule: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Notification helpers
     */
    private function notifyProducer($submission, $message)
    {
        // Find producer (assuming there's a producer_id field or we get it from team)
        // For now, we'll notify based on role
        $producers = \App\Models\User::where('role', 'Producer')->get();
        
        foreach ($producers as $producer) {
            MusicWorkflowNotification::create([
                'submission_id' => $submission->id,
                'user_id' => $producer->id,
                'notification_type' => 'producer_notification',
                'title' => 'Creative Work Update',
                'message' => $message,
            ]);
        }
    }

    private function notifyManagerProgram($submission, $message)
    {
        $managers = \App\Models\User::where('role', 'Manager Program')->get();
        
        foreach ($managers as $manager) {
            MusicWorkflowNotification::create([
                'submission_id' => $submission->id,
                'user_id' => $manager->id,
                'notification_type' => 'manager_notification',
                'title' => 'Budget Approval Required',
                'message' => $message,
            ]);
        }
    }

    private function notifyCreative($submission, $message)
    {
        $creatives = \App\Models\User::where('role', 'Creative')->get();
        
        foreach ($creatives as $creative) {
            MusicWorkflowNotification::create([
                'submission_id' => $submission->id,
                'user_id' => $creative->id,
                'notification_type' => 'creative_notification',
                'title' => 'Creative Work Needs Revision',
                'message' => $message,
            ]);
        }
    }
}






