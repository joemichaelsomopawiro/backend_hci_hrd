<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastingSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'platform',
        'schedule_date',
        'status',
        'title',
        'description',
        'tags',
        'url',
        'thumbnail_path',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'created_by',
        'uploaded_by',
        'uploaded_at',
        'published_at',
        'upload_notes',
        'error_message'
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'tags' => 'array',
        'uploaded_at' => 'datetime',
        'published_at' => 'datetime',
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
     * Relationship dengan User yang upload
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Upload to platform
     */
    public function upload(int $uploadedBy, string $filePath, ?string $notes = null): void
    {
        $this->update([
            'status' => 'uploading',
            'uploaded_by' => $uploadedBy,
            'file_path' => $filePath,
            'upload_notes' => $notes
        ]);
    }

    /**
     * Mark as uploaded
     */
    public function markAsUploaded(): void
    {
        $this->update([
            'status' => 'uploaded',
            'uploaded_at' => now()
        ]);
    }

    /**
     * Mark as published
     */
    public function markAsPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now()
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
     * Get file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
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
     * Get platform label
     */
    public function getPlatformLabelAttribute(): string
    {
        $labels = [
            'youtube' => 'YouTube',
            'website' => 'Website',
            'tv' => 'TV',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok'
        ];

        return $labels[$this->platform] ?? $this->platform;
    }

    /**
     * Scope berdasarkan platform
     */
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk schedule yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk schedule yang scheduled
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope untuk schedule yang uploading
     */
    public function scopeUploading($query)
    {
        return $query->where('status', 'uploading');
    }

    /**
     * Scope untuk schedule yang uploaded
     */
    public function scopeUploaded($query)
    {
        return $query->where('status', 'uploaded');
    }

    /**
     * Scope untuk schedule yang published
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope untuk schedule yang failed
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
