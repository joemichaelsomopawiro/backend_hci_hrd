<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrDistributionSchedule;
use App\Models\PrDistributionReport;
use Illuminate\Support\Facades\DB;

class PrDistributionService
{
    /**
     * Verify program untuk distribusi
     */
    public function verifyProgram(PrProgram $program, bool $verified, ?string $notes = null): PrProgram
    {
        $status = $verified ? 'distribusi_approved' : 'distribusi_rejected';
        
        $program->update([
            'status' => $status,
            'manager_distribusi_id' => auth()->id()
        ]);

        return $program->fresh();
    }

    /**
     * Create jadwal tayang
     */
    public function createDistributionSchedule(PrProgram $program, array $data, int $createdBy): PrDistributionSchedule
    {
        return DB::transaction(function () use ($program, $data, $createdBy) {
            // Update program status
            if ($program->status === 'distribusi_approved') {
                $program->update(['status' => 'scheduled']);
            }

            $schedule = PrDistributionSchedule::create([
                'program_id' => $program->id,
                'episode_id' => $data['episode_id'] ?? null,
                'schedule_date' => $data['schedule_date'],
                'schedule_time' => $data['schedule_time'],
                'channel' => $data['channel'] ?? null,
                'schedule_notes' => $data['schedule_notes'] ?? null,
                'status' => 'scheduled',
                'created_by' => $createdBy
            ]);

            return $schedule;
        });
    }

    /**
     * Mark episode as aired
     */
    public function markAsAired(PrEpisode $episode): PrEpisode
    {
        $episode->update(['status' => 'aired']);

        // Update program status jika semua episode sudah tayang
        $program = $episode->program;
        $allAired = $program->episodes()
            ->where('status', '!=', 'aired')
            ->count() === 0;

        if ($allAired) {
            $program->update(['status' => 'completed']);
        }

        return $episode->fresh();
    }

    /**
     * Create laporan distribusi
     */
    public function createDistributionReport(PrProgram $program, array $data, int $createdBy): PrDistributionReport
    {
        return PrDistributionReport::create([
            'program_id' => $program->id,
            'episode_id' => $data['episode_id'] ?? null,
            'report_title' => $data['report_title'],
            'report_content' => $data['report_content'],
            'distribution_data' => $data['distribution_data'] ?? null,
            'analytics_data' => $data['analytics_data'] ?? null,
            'report_period_start' => $data['report_period_start'] ?? null,
            'report_period_end' => $data['report_period_end'] ?? null,
            'status' => 'published',
            'created_by' => $createdBy
        ]);
    }

    /**
     * Get distribution reports
     */
    public function getDistributionReports(array $filters = [])
    {
        $query = PrDistributionReport::with(['program', 'episode', 'creator']);

        if (isset($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
