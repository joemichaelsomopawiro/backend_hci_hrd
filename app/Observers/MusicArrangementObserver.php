<?php

namespace App\Observers;

use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\Deadline;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class MusicArrangementObserver
{
    /**
     * Handle the MusicArrangement "updated" event.
     * Auto-create Creative Work when arrangement status changes to arrangement_approved
     */
    public function updated(MusicArrangement $arrangement): void
    {
        // Log semua perubahan status untuk debugging
        if ($arrangement->isDirty('status')) {
            Log::info('MusicArrangementObserver - Status changed', [
                'arrangement_id' => $arrangement->id,
                'episode_id' => $arrangement->episode_id,
                'old_status' => $arrangement->getOriginal('status'),
                'new_status' => $arrangement->status,
                'sound_engineer_helper_id' => $arrangement->sound_engineer_helper_id
            ]);
        }
        
        // Check if status changed to arrangement_approved
        // INI AKAN TRIGGER UNTUK SEMUA ARRANGEMENT YANG DI-APPROVE PRODUCER
        // Baik dari Music Arranger langsung maupun yang sudah dibantu Sound Engineer
        if ($arrangement->isDirty('status') && $arrangement->status === 'arrangement_approved') {
            Log::info('MusicArrangementObserver - Status changed to arrangement_approved - WILL CREATE CREATIVE WORK', [
                'arrangement_id' => $arrangement->id,
                'episode_id' => $arrangement->episode_id,
                'old_status' => $arrangement->getOriginal('status'),
                'new_status' => $arrangement->status,
                'sound_engineer_helper_id' => $arrangement->sound_engineer_helper_id,
                'is_from_sound_engineer' => !empty($arrangement->sound_engineer_helper_id),
                'note' => 'This will create Creative Work for ALL approved arrangements'
            ]);

            // Load episode with production team
            // Reload arrangement dengan relasi episode dan program untuk mendapatkan productionTeam
            $arrangement->load(['episode.program.productionTeam', 'episode.productionTeam']);
            $episode = $arrangement->episode;
            
            if (!$episode) {
                Log::warning('MusicArrangementObserver - Episode not found', [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id
                ]);
                return;
            }

            // Check if Creative Work already exists for this episode
            $existingCreativeWork = CreativeWork::where('episode_id', $episode->id)->first();
            
            if ($existingCreativeWork) {
                Log::info('MusicArrangementObserver - Creative Work already exists', [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $episode->id,
                    'existing_creative_work_id' => $existingCreativeWork->id,
                    'existing_creative_work_status' => $existingCreativeWork->status
                ]);
                return;
            }

            // Get Creative from production team
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $creativeUser = null;
            
            if ($productionTeam) {
                $creativeMember = $productionTeam->members()
                    ->where('role', 'creative')
                    ->where('is_active', true)
                    ->first();
                
                if ($creativeMember) {
                    $creativeUser = $creativeMember->user_id;
                }
            }
            
            // If no Creative in team, find any Creative user
            if (!$creativeUser) {
                $creative = \App\Models\User::where('role', 'Creative')->first();
                $creativeUser = $creative ? $creative->id : null;
            }
            
            // Check if arrangement was fixed by Sound Engineer
            $isFixedBySoundEngineer = !empty($arrangement->sound_engineer_helper_id);
            $scriptContent = "Creative work task for episode {$episode->episode_number}. Music arrangement '{$arrangement->song_title}' has been approved.";
            if ($isFixedBySoundEngineer) {
                $scriptContent .= " (Arrangement ini telah diperbaiki oleh Sound Engineer sebelum di-approve oleh Producer)";
            }
            
            // Create Creative Work task
            $creativeWork = CreativeWork::create([
                'episode_id' => $episode->id,
                'script_content' => $scriptContent,
                'status' => 'draft',
                'created_by' => $creativeUser ?? $arrangement->reviewed_by ?? auth()->id()
            ]);
            
            Log::info('MusicArrangementObserver - Creative Work created', [
                'arrangement_id' => $arrangement->id,
                'episode_id' => $episode->id,
                'creative_work_id' => $creativeWork->id,
                'creative_user_id' => $creativeUser,
                'is_fixed_by_sound_engineer' => $isFixedBySoundEngineer,
                'sound_engineer_helper_id' => $arrangement->sound_engineer_helper_id
            ]);
            
            // Notify Creative
            if ($creativeUser) {
                $message = "A new creative work task has been created for Episode {$episode->episode_number}. Music arrangement '{$arrangement->song_title}' has been approved.";
                if ($isFixedBySoundEngineer) {
                    $message .= " (Arrangement ini telah diperbaiki oleh Sound Engineer sebelum di-approve)";
                }
                $message .= " Silakan terima pekerjaan dan mulai buat script, storyboard, jadwal rekaman, jadwal syuting, lokasi syuting, dan budget talent.";
                
                Notification::create([
                    'user_id' => $creativeUser,
                    'type' => 'creative_work_created',
                    'title' => 'New Creative Work Task',
                    'message' => $message,
                    'data' => [
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $episode->id,
                        'arrangement_id' => $arrangement->id,
                        'is_fixed_by_sound_engineer' => $isFixedBySoundEngineer
                    ]
                ]);
            }

            // Auto-update episode workflow state if needed
            if (in_array($episode->current_workflow_state, ['music_arrangement', 'episode_generated'])) {
                $workflowService = app(\App\Services\WorkflowStateService::class);
                $workflowService->updateWorkflowState(
                    $episode,
                    'creative_work',
                    'Creative',
                    $creativeUser,
                    'Music arrangement approved, proceeding to creative work'
                );
            }

            // Mark music arrangement deadline as completed for progress tracking
            $deadline = Deadline::where('episode_id', $episode->id)
                ->where('role', 'musik_arr')
                ->first();
            
            if ($deadline && !$deadline->is_completed) {
                $deadline->markAsCompleted(
                    $arrangement->reviewed_by ?? auth()->id() ?? $arrangement->created_by,
                    'Arrangement approved by Producer'
                );
                Log::info('MusicArrangementObserver - Deadline marked as completed', [
                    'deadline_id' => $deadline->id,
                    'episode_id' => $episode->id,
                    'role' => 'musik_arr'
                ]);
            }
        }
    }
}

