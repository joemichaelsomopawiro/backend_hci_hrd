<?php

namespace App\Observers;

use App\Models\CreativeWork;
use App\Models\Deadline;
use Illuminate\Support\Facades\Log;

class CreativeWorkObserver
{
    /**
     * Handle the CreativeWork "updated" event.
     */
    public function updated(CreativeWork $creativeWork): void
    {
        // Sync deadlines when shooting schedule is updated
        if ($creativeWork->isDirty('shooting_schedule') && $creativeWork->shooting_schedule) {
            Log::info('CreativeWorkObserver - Shooting schedule updated, syncing deadlines', [
                'creative_work_id' => $creativeWork->id,
                'episode_id' => $creativeWork->episode_id,
                'new_shooting_schedule' => $creativeWork->shooting_schedule
            ]);

            try {
                $episode = $creativeWork->episode;
                if (!$episode) return;

                $rolesToSync = ['tim_syuting_coord', 'promotion_shooting', 'tim_setting_coord'];
                
                foreach ($rolesToSync as $role) {
                    $deadline = Deadline::where('episode_id', $episode->id)
                        ->where('role', $role)
                        ->where('is_completed', false) // Only sync pending deadlines
                        ->first();
                    
                    if ($deadline) {
                        $deadline->update([
                            'deadline_date' => $creativeWork->shooting_schedule,
                            'auto_generated' => true,
                            'change_reason' => 'Synced with Shooting Schedule'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('CreativeWorkObserver - Failed to sync deadlines: ' . $e->getMessage());
            }
        }
    }
}
