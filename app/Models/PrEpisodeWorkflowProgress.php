<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrEpisodeWorkflowProgress extends Model
{
    use HasFactory;

    protected $table = 'pr_episode_workflow_progress';

    protected $fillable = [
        'episode_id',
        'workflow_step',
        'step_name',
        'responsible_role',
        'assigned_user_id',
        'status',
        'started_at',
        'completed_at',
        'deadline_at',
        'notes'
    ];

    protected $casts = [
        'workflow_step' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'deadline_at' => 'datetime'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(PrEpisode::class, 'episode_id');
    }

    /**
     * Relationship dengan User (assigned user)
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Scope untuk filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter by workflow step
     */
    public function scopeByStep($query, int $step)
    {
        return $query->where('workflow_step', $step);
    }

    /**
     * Scope untuk get pending steps
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk get in progress steps
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope untuk get completed steps
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if step is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if step is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if step is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get duration of step completion (in hours)
     */
    public function getDurationAttribute(): ?float
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInHours($this->completed_at, true);
    }

    /**
     * Get responsible roles as array
     */
    public function getResponsibleRolesAttribute(): array
    {
        return array_map('trim', explode(',', $this->responsible_role));
    }
}
