<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrProductionSchedule;
use Illuminate\Support\Facades\DB;

class PrProductionService
{
    /**
     * Create jadwal produksi
     */
    public function createProductionSchedule(PrProgram $program, array $data, int $createdBy): PrProductionSchedule
    {
        return DB::transaction(function () use ($program, $data, $createdBy) {
            // Update program status jika belum
            if ($program->status === 'concept_approved') {
                $program->update(['status' => 'production_scheduled']);
            }

            $schedule = PrProductionSchedule::create([
                'program_id' => $program->id,
                'episode_id' => $data['episode_id'] ?? null,
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'] ?? null,
                'schedule_notes' => $data['schedule_notes'] ?? null,
                'status' => 'confirmed',
                'created_by' => $createdBy
            ]);

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

        // Update program status
        $program = $episode->program;
        if ($status === 'production' && $program->status !== 'in_production') {
            $program->update(['status' => 'in_production']);
        } elseif ($status === 'editing' && $program->status !== 'editing') {
            $program->update(['status' => 'editing']);
        }

        return $episode->fresh();
    }

    /**
     * Submit episode untuk review Manager Program
     */
    public function submitForReview(PrEpisode $episode): PrEpisode
    {
        $episode->update(['status' => 'ready_for_review']);

        // Update program status jika semua episode ready
        $program = $episode->program;
        $allEpisodesReady = $program->episodes()
            ->where('status', '!=', 'ready_for_review')
            ->where('status', '!=', 'manager_approved')
            ->where('status', '!=', 'aired')
            ->count() === 0;

        if ($allEpisodesReady) {
            $program->update(['status' => 'submitted_to_manager']);
        }

        return $episode->fresh();
    }
}
