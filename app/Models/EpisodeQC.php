<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Episode QC Model
 * 
 * Menyimpan review QC untuk setiap episode
 */
class EpisodeQC extends Model
{
    use HasFactory;

    protected $table = 'episode_qc';

    protected $fillable = [
        'program_episode_id',
        'qc_by',
        'decision',
        'quality_score',
        'video_quality_score',
        'audio_quality_score',
        'content_quality_score',
        'notes',
        'revision_points',
        'reviewed_at',
        'status'
    ];

    protected $casts = [
        'revision_points' => 'array',
        'reviewed_at' => 'datetime'
    ];

    /**
     * Relasi dengan ProgramEpisode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(ProgramEpisode::class, 'program_episode_id');
    }

    /**
     * Relasi dengan User (QC reviewer)
     */
    public function qcBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_by');
    }

    /**
     * Scope untuk approved QC
     */
    public function scopeApproved($query)
    {
        return $query->where('decision', 'approved');
    }

    /**
     * Scope untuk revision needed
     */
    public function scopeRevisionNeeded($query)
    {
        return $query->where('decision', 'revision_needed');
    }

    /**
     * Check if QC is approved
     */
    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    /**
     * Check if revision is needed
     */
    public function needsRevision(): bool
    {
        return $this->decision === 'revision_needed';
    }

    /**
     * Get quality score label
     */
    public function getQualityScoreLabel(): string
    {
        if ($this->quality_score >= 9) return 'Excellent';
        if ($this->quality_score >= 7) return 'Good';
        if ($this->quality_score >= 5) return 'Fair';
        return 'Needs Improvement';
    }

    /**
     * Get high priority revision points
     */
    public function getHighPriorityRevisions(): array
    {
        if (!$this->revision_points) return [];
        
        return array_filter($this->revision_points, function($point) {
            return in_array($point['priority'] ?? 'medium', ['high', 'critical']);
        });
    }
}

