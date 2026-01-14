<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrProgramService
{
    /**
     * Create new program regular dengan auto-generate 53 episode
     */
    public function createProgram(array $data, int $managerProgramId): PrProgram
    {
        return DB::transaction(function () use ($data, $managerProgramId) {
            // Set program_year jika belum ada
            if (!isset($data['program_year'])) {
                $data['program_year'] = Carbon::now()->year;
            }

            // Create program
            $program = PrProgram::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'manager_program_id' => $managerProgramId,
                'start_date' => $data['start_date'],
                'air_time' => $data['air_time'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'broadcast_channel' => $data['broadcast_channel'] ?? null,
                'program_year' => $data['program_year'],
                'status' => 'draft'
            ]);

            // Auto-generate 53 episodes
            $program->generateEpisodes();

            return $program;
        });
    }

    /**
     * Update program status
     */
    public function updateStatus(PrProgram $program, string $status, ?int $userId = null): PrProgram
    {
        $updateData = ['status' => $status];

        // Set producer_id jika status production_scheduled
        if ($status === 'production_scheduled' && $userId) {
            $updateData['producer_id'] = $userId;
        }

        // Set manager_distribusi_id jika status submitted_to_distribusi
        if ($status === 'submitted_to_distribusi' && $userId) {
            $updateData['manager_distribusi_id'] = $userId;
        }

        $program->update($updateData);
        return $program->fresh();
    }

    /**
     * Generate episodes untuk tahun baru
     */
    public function generateEpisodesForNewYear(PrProgram $program, int $newYear): void
    {
        DB::transaction(function () use ($program, $newYear) {
            // Update program year
            $program->update(['program_year' => $newYear]);

            // Generate episodes untuk tahun baru
            $startDate = Carbon::create($newYear, 1, 1);
            $airTime = Carbon::parse($program->air_time)->format('H:i:s');

            for ($i = 1; $i <= 53; $i++) {
                $airDate = $startDate->copy()->addWeeks($i - 1);

                $program->episodes()->create([
                    'episode_number' => $i,
                    'title' => "Episode {$i} - {$newYear}",
                    'description' => "Episode {$i} dari program {$program->name} tahun {$newYear}",
                    'air_date' => $airDate,
                    'air_time' => $airTime,
                    'status' => 'scheduled'
                ]);
            }
        });
    }

    /**
     * Get programs dengan filter berdasarkan role
     */
    public function getPrograms(array $filters = [], ?User $user = null)
    {
        $query = PrProgram::with(['managerProgram', 'producer', 'managerDistribusi', 'episodes']);

        // Filter berdasarkan status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter berdasarkan tahun
        if (isset($filters['program_year'])) {
            $query->where('program_year', $filters['program_year']);
        }

        // Filter berdasarkan manager program
        if (isset($filters['manager_program_id'])) {
            $query->where('manager_program_id', $filters['manager_program_id']);
        }

        // Filter berdasarkan producer
        if (isset($filters['producer_id'])) {
            $query->where('producer_id', $filters['producer_id']);
        }

        // Search
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc');
    }
}
