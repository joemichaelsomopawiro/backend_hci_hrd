<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'file_type',
        'file_path',
        'file_name',
        'file_extension',
        'file_size',
        'mime_type',
        'storage_disk',
        'status',
        'file_description',
        'metadata',
        'thumbnail_path',
        'duration',
        'dimensions',
        'uploaded_by',
        'uploaded_at',
        'processed_at',
        'error_message'
    ];

    protected $casts = [
        'metadata' => 'array',
        'dimensions' => 'array',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'file_size' => 'integer',
        'duration' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang upload
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
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
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) return 'N/A';
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get formatted dimensions
     */
    public function getFormattedDimensionsAttribute(): string
    {
        if (!$this->dimensions) return 'N/A';
        
        return $this->dimensions['width'] . 'x' . $this->dimensions['height'];
    }

    /**
     * Scope berdasarkan file type
     */
    public function scopeByFileType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk file yang uploaded
     */
    public function scopeUploaded($query)
    {
        return $query->where('status', 'uploaded');
    }

    /**
     * Scope untuk file yang processed
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope untuk file yang failed
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}