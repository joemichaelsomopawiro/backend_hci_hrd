<?php

namespace App\Services;

use App\Models\Episode;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk handle assignment logic dengan auto-revert
 * untuk task yang di-reassign
 */
class WorkAssignmentService
{
    /**
     * Get the next assignee for a work item based on previous episode
     * Handles auto-revert logic for reassigned tasks
     * 
     * @param string $workModelClass - Class name of work model (e.g., 'App\Models\EditorWork')
     * @param int $programId - Program ID
     * @param int $currentEpisodeNumber - Current episode number being created
     * @param string|null $workType - Work type (e.g., 'editing', 'mixing') if applicable
     * @param int|null $fallbackUserId - Fallback user ID if no previous work found (usually auth()->user()->id)
     * @return int - User ID to assign the new work to
     */
    public static function getNextAssignee(
        string $workModelClass,
        int $programId,
        int $currentEpisodeNumber,
        ?string $workType = null,
        ?int $fallbackUserId = null
    ): int {
        // Find previous episode
        $previousEpisode = Episode::where('program_id', $programId)
            ->where('episode_number', $currentEpisodeNumber - 1)
            ->first();

        // If no previous episode (first episode), use fallback
        if (!$previousEpisode) {
            Log::info("WorkAssignmentService: No previous episode found for Program {$programId}, Episode {$currentEpisodeNumber}. Using fallback user.");
            return $fallbackUserId ?? auth()->user()->id;
        }

        // Find previous work for same type
        $query = $workModelClass::where('episode_id', $previousEpisode->id);
        
        // Add work_type filter if provided
        if ($workType !== null) {
            $query->where('work_type', $workType);
        }

        $previousWork = $query->first();

        // If no previous work, use fallback
        if (!$previousWork) {
            Log::info("WorkAssignmentService: No previous work found in {$workModelClass} for Episode {$previousEpisode->id}. Using fallback user.");
            return $fallbackUserId ?? auth()->user()->id;
        }

        // CRITICAL: Check if previous work was reassigned
        if ($previousWork->was_reassigned && $previousWork->originally_assigned_to) {
            // AUTO-REVERT to original assignee
            Log::info("WorkAssignmentService: Previous work in {$workModelClass} was reassigned. Reverting to original assignee (User {$previousWork->originally_assigned_to}).");
            return $previousWork->originally_assigned_to;
        }

        // Normal flow: use previous work's assignee
        $assignedUserId = $previousWork->created_by ?? ($fallbackUserId ?? auth()->user()->id);
        Log::info("WorkAssignmentService: Using previous work assignee (User {$assignedUserId}) for {$workModelClass}.");
        
        return $assignedUserId;
    }

    /**
     * Validate that work model class has required fields
     * 
     * @param string $workModelClass
     * @return bool
     */
    public static function validateWorkModel(string $workModelClass): bool
    {
        if (!class_exists($workModelClass)) {
            Log::error("WorkAssignmentService: Model class {$workModelClass} does not exist.");
            return false;
        }

        $model = new $workModelClass();
        $fillable = $model->getFillable();

        $requiredFields = ['created_by', 'originally_assigned_to', 'was_reassigned'];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $fillable)) {
                Log::warning("WorkAssignmentService: Model {$workModelClass} missing required field: {$field}");
            }
        }

        return true;
    }

    /**
     * Get assignment statistics for debugging
     * 
     * @param int $programId
     * @param int $episodeNumber
     * @return array
     */
    public static function getAssignmentStats(int $programId, int $episodeNumber): array
    {
        $episode = Episode::where('program_id', $programId)
            ->where('episode_number', $episodeNumber)
            ->first();

        if (!$episode) {
            return ['error' => 'Episode not found'];
        }

        // Get all work types for this episode
        $stats = [
            'program_id' => $programId,
            'episode_number' => $episodeNumber,
            'episode_id' => $episode->id,
            'works' => []
        ];

        // Check common work models
        $workModels = [
            'EditorWork' => \App\Models\EditorWork::class,
            'CreativeWork' => \App\Models\CreativeWork::class,
            'MusicArrangement' => \App\Models\MusicArrangement::class,
            'DesignGrafisWork' => \App\Models\DesignGrafisWork::class,
        ];

        foreach ($workModels as $name => $class) {
            if (!class_exists($class)) {
                continue;
            }

            $works = $class::where('episode_id', $episode->id)->get();
            
            foreach ($works as $work) {
                $stats['works'][] = [
                    'type' => $name,
                    'id' => $work->id,
                    'created_by' => $work->created_by,
                    'originally_assigned_to' => $work->originally_assigned_to ?? null,
                    'was_reassigned' => $work->was_reassigned ?? false,
                    'work_type' => $work->work_type ?? 'N/A'
                ];
            }
        }

        return $stats;
    }
}
