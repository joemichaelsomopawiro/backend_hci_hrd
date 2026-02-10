<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_episode_id',
        'step_number',
        'step_name',
        'is_completed',
        'completed_at',
        'completed_by',
        'completion_notes',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Helper Methods
    public function markAsCompleted(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId,
            'completion_notes' => $notes,
        ]);
    }

    public function markAsIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'completion_notes' => null,
        ]);
    }

    /**
     * Get workflow step names
     */
    public static function getStepNames(): array
    {
        return [
            1 => 'Kreatif',
            2 => 'Producer',
            3 => 'Manager Program',
            4 => 'Kreatif (Revision)',
            5 => 'Produksi',
            6 => 'Editor',
            7 => 'QC',
            8 => 'Broadcasting',
        ];
    }

    /**
     * Create workflow steps for an episode
     */
    public static function createForEpisode(int $episodeId): void
    {
        $stepNames = self::getStepNames();

        foreach ($stepNames as $stepNumber => $stepName) {
            self::firstOrCreate(
                [
                    'pr_episode_id' => $episodeId,
                    'step_number' => $stepNumber,
                ],
                [
                    'step_name' => $stepName,
                    'is_completed' => false,
                ]
            );
        }
    }
}
