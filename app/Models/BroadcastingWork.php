<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastingWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'editor_work_id',
        'created_by',
        'work_type',
        'title',
        'description',
        'video_file_path',
        'file_link',
        'thumbnail_path',
        'youtube_video_id',
        'youtube_url',
        'website_url',
        'playlist_data',
        'scheduled_time',
        'published_time',
        'status',
        'upload_progress',
        'platform_responses',
        'error_message',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_notes'
    ];

    protected $casts = [
        'metadata' => 'array',
        'playlist_data' => 'array',
        'upload_progress' => 'array',
        'platform_responses' => 'array',
        'scheduled_time' => 'datetime',
        'published_time' => 'datetime',
        'editor_work_id' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan EditorWork
     */
    public function editorWork(): BelongsTo
    {
        return $this->belongsTo(EditorWork::class);
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope untuk work yang preparing
     */
    public function scopePreparing($query)
    {
        return $query->where('status', 'preparing');
    }

    /**
     * Scope untuk work yang uploading
     */
    public function scopeUploading($query)
    {
        return $query->where('status', 'uploading');
    }

    /**
     * Scope untuk work yang published
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope untuk work yang scheduled
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope berdasarkan work type
     */
    public function scopeByWorkType($query, $workType)
    {
        return $query->where('work_type', $workType);
    }

    /**
     * Check if work is ready for upload
     */
    public function isReadyForUpload(): bool
    {
        return $this->status === 'preparing' && 
               $this->video_file_path && 
               $this->title &&
               $this->description;
    }

    /**
     * Mark as uploading
     */
    public function markAsUploading(): void
    {
        $this->update(['status' => 'uploading']);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark as published
     */
    public function markAsPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_time' => now()
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Update upload progress
     */
    public function updateUploadProgress(int $percentage, string $stage = 'uploading'): void
    {
        $this->update([
            'upload_progress' => [
                'percentage' => $percentage,
                'stage' => $stage,
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Get upload progress percentage
     */
    public function getUploadProgressPercentageAttribute(): int
    {
        return $this->upload_progress['percentage'] ?? 0;
    }
}













