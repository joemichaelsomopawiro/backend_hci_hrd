<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicArrangement extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'song_title',
        'singer_name',
        'arrangement_notes',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'file_size' => 'integer'
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
     * Submit arrangement for review
     */
    public function submitForReview(): void
    {
        $this->update(['status' => 'submitted']);
    }

    /**
     * Approve arrangement
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
     * Reject arrangement
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejection_reason' => $reason
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
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk arrangement yang submitted
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope untuk arrangement yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk arrangement yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
