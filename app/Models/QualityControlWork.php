<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityControlWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'created_by',
        'qc_type',
        'title',
        'description',
        'files_to_check',
        'qc_checklist',
        'quality_standards',
        'quality_score',
        'issues_found',
        'improvements_needed',
        'qc_notes',
        'screenshots',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes'
    ];

    protected $casts = [
        'files_to_check' => 'array',
        'qc_checklist' => 'array',
        'quality_standards' => 'array',
        'issues_found' => 'array',
        'improvements_needed' => 'array',
        'screenshots' => 'array',
        'reviewed_at' => 'datetime'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang review
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope untuk QC yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk QC yang in progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope untuk QC yang passed
     */
    public function scopePassed($query)
    {
        return $query->where('status', 'passed');
    }

    /**
     * Scope untuk QC yang failed
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope berdasarkan QC type
     */
    public function scopeByQcType($query, $qcType)
    {
        return $query->where('qc_type', $qcType);
    }

    /**
     * Check if QC is ready for review
     */
    public function isReadyForReview(): bool
    {
        return $this->status === 'in_progress' && 
               $this->quality_score !== null &&
               $this->qc_notes;
    }

    /**
     * Mark as in progress
     */
    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    /**
     * Mark as passed
     */
    public function markAsPassed(): void
    {
        $this->update(['status' => 'passed']);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Mark as revision needed
     */
    public function markAsRevisionNeeded(): void
    {
        $this->update(['status' => 'revision_needed']);
    }

    /**
     * Mark as approved
     */
    public function markAsApproved(): void
    {
        $this->update(['status' => 'approved']);
    }

    /**
     * Get quality grade
     */
    public function getQualityGradeAttribute(): string
    {
        if ($this->quality_score >= 90) {
            return 'A';
        } elseif ($this->quality_score >= 80) {
            return 'B';
        } elseif ($this->quality_score >= 70) {
            return 'C';
        } elseif ($this->quality_score >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }

    /**
     * Check if QC passed minimum standards
     */
    public function passedMinimumStandards(): bool
    {
        return $this->quality_score >= 70;
    }
}













