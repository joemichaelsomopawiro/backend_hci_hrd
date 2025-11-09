<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowState;
use Illuminate\Support\Facades\DB;

class WorkflowTransitionService
{
    /**
     * Transition episode to next workflow state
     */
    public function transitionEpisode(int $episodeId, string $newState, ?int $userId = null): bool
    {
        try {
            DB::beginTransaction();

            $episode = Episode::findOrFail($episodeId);
            $oldState = $episode->current_workflow_state;

            // Update episode state
            $episode->update([
                'current_workflow_state' => $newState,
                'assigned_to_user' => $userId
            ]);

            // Create workflow state record
            WorkflowState::create([
                'episode_id' => $episodeId,
                'state' => $newState,
                'previous_state' => $oldState,
                'changed_by' => $userId,
                'notes' => "Transitioned from {$oldState} to {$newState}"
            ]);

            // Send notifications based on new state
            $this->sendStateNotifications($episode, $newState, $oldState);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Auto-transition based on work completion
     */
    public function autoTransitionOnCompletion(int $episodeId, string $completedWorkType): bool
    {
        try {
            $episode = Episode::findOrFail($episodeId);
            $currentState = $episode->current_workflow_state;

            $nextState = $this->getNextStateForCompletedWork($currentState, $completedWorkType);
            
            if ($nextState) {
                return $this->transitionEpisode($episodeId, $nextState);
            }

            return false;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get next state based on completed work
     */
    private function getNextStateForCompletedWork(string $currentState, string $completedWorkType): ?string
    {
        $transitions = [
            'music_arrangement_completed' => [
                'music_arrangement' => 'creative_work',
                'creative_work' => 'producer_approval'
            ],
            'creative_work_completed' => [
                'creative_work' => 'producer_approval',
                'producer_approval' => 'production_planning'
            ],
            'producer_approval_completed' => [
                'producer_approval' => 'production_planning',
                'production_planning' => 'equipment_request'
            ],
            'equipment_approved' => [
                'equipment_request' => 'production',
                'production' => 'sound_engineering'
            ],
            'sound_engineering_completed' => [
                'sound_engineering' => 'editing',
                'editing' => 'quality_control'
            ],
            'editing_completed' => [
                'editing' => 'quality_control',
                'quality_control' => 'broadcasting'
            ],
            'quality_control_approved' => [
                'quality_control' => 'broadcasting',
                'broadcasting' => 'published'
            ]
        ];

        return $transitions[$completedWorkType][$currentState] ?? null;
    }

    /**
     * Send notifications based on workflow state
     */
    private function sendStateNotifications(Episode $episode, string $newState, string $oldState): void
    {
        $notifications = $this->getNotificationsForState($newState, $episode);
        
        foreach ($notifications as $notification) {
            Notification::create([
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'user_id' => $notification['user_id'],
                'episode_id' => $episode->id
            ]);
        }
    }

    /**
     * Get notifications for specific state
     */
    private function getNotificationsForState(string $state, Episode $episode): array
    {
        $notifications = [];

        switch ($state) {
            case 'creative_work':
                $creativeUsers = User::where('role', 'Creative')->get();
                foreach ($creativeUsers as $user) {
                    $notifications[] = [
                        'title' => 'Creative Work Assigned',
                        'message' => "Episode {$episode->episode_number} requires creative work",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'producer_approval':
                $producers = User::where('role', 'Producer')->get();
                foreach ($producers as $user) {
                    $notifications[] = [
                        'title' => 'Producer Approval Required',
                        'message' => "Episode {$episode->episode_number} requires producer approval",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'equipment_request':
                $artSetUsers = User::where('role', 'Art & Set Properti')->get();
                foreach ($artSetUsers as $user) {
                    $notifications[] = [
                        'title' => 'Equipment Request Required',
                        'message' => "Episode {$episode->episode_number} requires equipment approval",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'production':
                $produksiUsers = User::where('role', 'Produksi')->get();
                foreach ($produksiUsers as $user) {
                    $notifications[] = [
                        'title' => 'Production Work Assigned',
                        'message' => "Episode {$episode->episode_number} ready for production",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'sound_engineering':
                $soundEngineers = User::where('role', 'Sound Engineer')->get();
                foreach ($soundEngineers as $user) {
                    $notifications[] = [
                        'title' => 'Sound Engineering Required',
                        'message' => "Episode {$episode->episode_number} ready for sound engineering",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'editing':
                $editors = User::where('role', 'Editor')->get();
                foreach ($editors as $user) {
                    $notifications[] = [
                        'title' => 'Editing Work Assigned',
                        'message' => "Episode {$episode->episode_number} ready for editing",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'quality_control':
                $qcUsers = User::where('role', 'Quality Control')->get();
                foreach ($qcUsers as $user) {
                    $notifications[] = [
                        'title' => 'Quality Control Required',
                        'message' => "Episode {$episode->episode_number} ready for quality control",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'broadcasting':
                $broadcastingUsers = User::where('role', 'Broadcasting')->get();
                foreach ($broadcastingUsers as $user) {
                    $notifications[] = [
                        'title' => 'Broadcasting Required',
                        'message' => "Episode {$episode->episode_number} ready for broadcasting",
                        'type' => 'workflow_transition',
                        'user_id' => $user->id
                    ];
                }
                break;

            case 'published':
                $managerUsers = User::where('role', 'Manager Program')->get();
                foreach ($managerUsers as $user) {
                    $notifications[] = [
                        'title' => 'Episode Published',
                        'message' => "Episode {$episode->episode_number} has been published successfully",
                        'type' => 'workflow_completed',
                        'user_id' => $user->id
                    ];
                }
                break;
        }

        return $notifications;
    }

    /**
     * Get workflow transitions for episode
     */
    public function getEpisodeTransitions(int $episodeId): array
    {
        $transitions = WorkflowState::where('episode_id', $episodeId)
            ->with(['changedBy'])
            ->orderBy('created_at', 'asc')
            ->get();

        return $transitions->map(function ($transition) {
            return [
                'id' => $transition->id,
                'state' => $transition->state,
                'previous_state' => $transition->previous_state,
                'changed_by' => $transition->changedBy ? $transition->changedBy->name : 'System',
                'changed_at' => $transition->created_at,
                'notes' => $transition->notes
            ];
        })->toArray();
    }

    /**
     * Get available next states for current state
     */
    public function getAvailableNextStates(string $currentState): array
    {
        $stateTransitions = [
            'music_arrangement' => ['creative_work', 'producer_approval'],
            'creative_work' => ['producer_approval'],
            'producer_approval' => ['production_planning', 'creative_work'],
            'production_planning' => ['equipment_request'],
            'equipment_request' => ['production'],
            'production' => ['sound_engineering', 'editing'],
            'sound_engineering' => ['editing'],
            'editing' => ['quality_control'],
            'quality_control' => ['broadcasting', 'editing'],
            'broadcasting' => ['published']
        ];

        return $stateTransitions[$currentState] ?? [];
    }
}













