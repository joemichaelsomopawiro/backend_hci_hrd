<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorPromosiWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'work_type',
        'file_links',
        'status',
        'created_by',
        'reviewed_by',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'originally_assigned_to',
        'was_reassigned'
    ];

    protected $casts = [
        'file_links' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeInQC($query)
    {
        return $query->where('status', 'in_qc');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Helper Methods
     */
    public function submitToQC()
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);
    }

    public function approve(int $reviewerId, ?string $notes = null)
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'review_notes' => $notes,
            'reviewed_at' => now()
        ]);
    }

    public function reject(int $reviewerId, string $reason)
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'review_notes' => $reason,
            'reviewed_at' => now()
        ]);
    }

    public function startQC()
    {
        $this->update([
            'status' => 'in_qc'
        ]);
    }

    /**
     * Accessors
     */
    public function getWorkTypeLabelAttribute(): string
    {
        $labels = [
            'bts_video' => 'Video BTS (Behind The Scenes)',
            'iklan_tv' => 'Iklan Episode TV',
            'highlight_ig' => 'Highlight Instagram',
            'highlight_tv' => 'Highlight TV',
            'highlight_facebook' => 'Highlight Facebook',
            'teaser' => 'Teaser',
            'trailer' => 'Trailer'
        ];

        return $labels[$this->work_type] ?? $this->work_type;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'in_progress' => 'In Progress',
            'submitted' => 'Submitted to QC',
            'in_qc' => 'Under QC Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'gray',
            'in_progress' => 'blue',
            'submitted' => 'yellow',
            'in_qc' => 'orange',
            'approved' => 'green',
            'rejected' => 'red'
        ];

        return $colors[$this->status] ?? 'gray';
    }
}
