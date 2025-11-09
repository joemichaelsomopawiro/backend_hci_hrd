<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'work_type',
        'editing_notes',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'source_files',
        'file_notes',
        'file_complete',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'qc_feedback'
    ];

    protected $casts = [
        'source_files' => 'array',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
        'file_complete' => 'boolean'
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
     * Submit work for review
     */
    public function submitForReview(): void
    {
        $this->update(['status' => 'submitted']);
    }

    /**
     * Approve work
     */
    public function approve(int $reviewedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Reject work
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $reason
        ]);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get work type label
     */
    public function getWorkTypeLabelAttribute(): string
    {
        $labels = [
            'main_episode' => 'Main Episode',
            'bts' => 'BTS',
            'highlight_ig' => 'Highlight Instagram',
            'highlight_tv' => 'Highlight TV',
            'highlight_facebook' => 'Highlight Facebook',
            'advertisement' => 'Advertisement'
        ];

        return $labels[$this->work_type] ?? $this->work_type;
    }

    /**
     * Scope berdasarkan work type
     */
    public function scopeByWorkType($query, $type)
    {
        return $query->where('work_type', $type);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk work yang draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope untuk work yang editing
     */
    public function scopeEditing($query)
    {
        return $query->where('status', 'editing');
    }

    /**
     * Scope untuk work yang completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope untuk work yang reviewed
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope untuk work yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}