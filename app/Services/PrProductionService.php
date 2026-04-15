<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrProductionSchedule;
use App\Models\PrPromotionWork;
use Illuminate\Support\Facades\DB;

class PrProductionService
{
    /**
     * Create jadwal produksi
     */
    public function createProductionSchedule(PrProgram $program, array $data, int $createdBy): PrProductionSchedule
    {
        return DB::transaction(function () use ($program, $data, $createdBy) {
            // No status change needed, just remain active

            $schedule = PrProductionSchedule::create([
                'program_id' => $program->id,
                'episode_id' => $data['episode_id'] ?? null,
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'] ?? null,
                'scheduled_location' => $data['scheduled_location'] ?? null,
                'schedule_notes' => $data['schedule_notes'] ?? null,
                'status' => 'confirmed',
                'created_by' => $createdBy
            ]);

            // Sync to episode and creative work
            if ($schedule->episode_id) {
                $this->syncScheduleToEpisodeAndCreativeWork($schedule);
            }

            return $schedule;
        });
    }

    /**
     * Update episode status untuk produksi
     */
    public function updateEpisodeStatus(PrEpisode $episode, string $status, ?string $notes = null): PrEpisode
    {
        $updateData = ['status' => $status];

        if ($status === 'production') {
            $updateData['production_notes'] = $notes;
        } elseif ($status === 'editing') {
            $updateData['editing_notes'] = $notes;
        }

        $episode->update($updateData);

        // Program status remains 'active' as long as production/editing is happening

        return $episode->fresh();
    }

    /**
     * Submit episode untuk review Manager Program
     */
    public function submitForReview(PrEpisode $episode): PrEpisode
    {
        $episode->update(['status' => 'ready_for_review']);

        // Program status remains 'active' even if all episodes are ready for review

        return $episode->fresh();
    }

    /**
     * Reschedule production schedule
     */
    public function rescheduleProductionSchedule(PrProductionSchedule $oldSchedule, array $newData, int $createdBy): PrProductionSchedule
    {
        return DB::transaction(function () use ($oldSchedule, $newData, $createdBy) {
            // Cancel old schedule
            $oldSchedule->update(['status' => 'cancelled']);

            // Create new schedule
            $newSchedule = PrProductionSchedule::create([
                'program_id' => $oldSchedule->program_id,
                'episode_id' => $oldSchedule->episode_id,
                'scheduled_date' => $newData['scheduled_date'],
                'scheduled_time' => $newData['scheduled_time'] ?? null,
                'scheduled_location' => $newData['scheduled_location'] ?? null,
                'schedule_notes' => $newData['schedule_notes'] ?? null,
                'status' => 'confirmed',
                'created_by' => $createdBy
            ]);

            // Sync to episode and creative work
            if ($newSchedule->episode_id) {
                $this->syncScheduleToEpisodeAndCreativeWork($newSchedule);
            }

            return $newSchedule;
        });
    }

    /**
     * Sinkronisasi jadwal produksi ke Episode dan Creative Work
     */
    public function syncScheduleToEpisodeAndCreativeWork(PrProductionSchedule $schedule): void
    {
        $episode = $schedule->episode;
        if (!$episode) return;

        // 1. Update Episode production_date
        $episode->update([
            'production_date' => $schedule->scheduled_date
        ]);

        // 2. Update Creative Work shooting schedule and location
        $creativeWork = $episode->creativeWork;
        if ($creativeWork) {
            $shootingSchedule = null;
            if ($schedule->scheduled_date) {
                $dateStr = null;
                if ($schedule->scheduled_date instanceof \DateTimeInterface) {
                    $dateStr = $schedule->scheduled_date->format('Y-m-d');
                } else {
                    $dateStr = date('Y-m-d', strtotime($schedule->scheduled_date));
                }

                $timeStr = '00:00:00';
                if ($schedule->scheduled_time) {
                     if ($schedule->scheduled_time instanceof \DateTimeInterface) {
                        $timeStr = $schedule->scheduled_time->format('H:i:s');
                     } else {
                        $timeStr = date('H:i:s', strtotime($schedule->scheduled_time));
                     }
                }
                
                $shootingSchedule = $dateStr . ' ' . $timeStr;
            }

            $creativeWork->update([
                'shooting_schedule' => $shootingSchedule,
                'shooting_location' => $schedule->scheduled_location
            ]);
        }

        // 3. Update Promotion Work if exists
        $promotionWork = PrPromotionWork::where('pr_episode_id', $episode->id)->first();
        if ($promotionWork) {
            $promotionWork->update([
                'shooting_date' => $schedule->scheduled_date,
                'shooting_time' => $schedule->scheduled_time,
                'location_data' => $schedule->scheduled_location
            ]);
        }
    }
}
