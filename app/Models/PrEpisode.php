<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrEpisode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pr_episodes';

    protected $fillable = [
        'program_id',
        'episode_number',
        'title',
        'description',
        'air_date',
        'air_time',
        'production_date',
        'status',
        'production_notes',
        'editing_notes'
    ];

    protected $casts = [
        'air_date' => 'date',
        'air_time' => 'datetime:H:i',
        'production_date' => 'date'
    ];

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PrProgram::class, 'program_id');
    }

    /**
     * Relationship dengan Creative Work
     */
    public function creativeWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrCreativeWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Production Work
     */
    public function productionWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrProduksiWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Editor Work
     */
    public function editorWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrEditorWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Promotion Work
     */
    public function promotionWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrPromotionWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Editor Promosi Work
     */
    public function editorPromosiWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrEditorPromosiWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Design Grafis Work
     */
    public function designGrafisWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrDesignGrafisWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Quality Control Work
     */
    public function qualityControlWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrQualityControlWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Manager Distribusi QC Work
     */
    public function managerDistribusiQcWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrManagerDistribusiQcWork::class, 'pr_episode_id');
    }

    /**
     * Relationship dengan Broadcasting Work
     */
    public function broadcastingWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrBroadcastingWork::class, 'pr_episode_id');
    }
    /**
     * Relationship dengan Production Schedules
     */
    public function productionSchedules(): HasMany
    {
        return $this->hasMany(PrProductionSchedule::class, 'episode_id');
    }

    /**
     * Relationship dengan Program Files
     */
    public function files(): HasMany
    {
        return $this->hasMany(PrProgramFile::class, 'episode_id');
    }

    /**
     * Relationship dengan Distribution Schedules
     */
    public function distributionSchedules(): HasMany
    {
        return $this->hasMany(PrDistributionSchedule::class, 'episode_id');
    }

    /**
     * Relationship dengan Distribution Reports
     */
    public function distributionReports(): HasMany
    {
        return $this->hasMany(PrDistributionReport::class, 'episode_id');
    }

    /**
     * Relationship dengan Workflow Progress
     */
    public function workflowProgress(): HasMany
    {
        return $this->hasMany(PrEpisodeWorkflowProgress::class, 'episode_id')->orderBy('workflow_step');
    }

    /**
     * Get current workflow step
     */
    public function currentWorkflowStep()
    {
        return $this->workflowProgress()
            ->where('status', '!=', 'completed')
            ->orderBy('workflow_step')
            ->first();
    }

    /**
     * Get workflow completion percentage
     */
    public function getWorkflowCompletionAttribute(): float
    {
        $total = $this->workflowProgress()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->workflowProgress()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get edited video file
     */
    public function editedVideo()
    {
        return $this->files()
            ->where('category', 'edited_video')
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    /**
     * Check if episode is ready for review
     */
    public function isReadyForReview(): bool
    {
        return $this->status === 'ready_for_review';
    }

    /**
     * Relationship dengan Episode Crews
     */
    public function crews(): HasMany
    {
        return $this->hasMany(PrEpisodeCrew::class, 'episode_id');
    }

    /**
     * Check if episode is approved by manager
     */
    public function isManagerApproved(): bool
    {
        return $this->status === 'manager_approved';
    }
}
