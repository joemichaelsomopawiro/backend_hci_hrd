<?php

namespace App\Services;

use App\Models\PrActivityLog;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use Illuminate\Support\Facades\Auth;

class PrActivityLogService
{
    /**
     * Log activity for a program
     */
    public function logProgramActivity(PrProgram $program, string $action, string $description, ?array $changes = null, ?int $userId = null): PrActivityLog
    {
        return PrActivityLog::create([
            'program_id' => $program->id,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'changes' => $changes
        ]);
    }

    /**
     * Log activity for an episode
     */
    public function logEpisodeActivity(PrEpisode $episode, string $action, string $description, ?array $changes = null, ?int $userId = null): PrActivityLog
    {
        return PrActivityLog::create([
            'program_id' => $episode->program_id,
            'episode_id' => $episode->id,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'changes' => $changes
        ]);
    }

    /**
     * Get activity history for a program
     */
    public function getProgramHistory(int $programId)
    {
        return PrActivityLog::with(['user', 'episode'])
            ->where('program_id', $programId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
