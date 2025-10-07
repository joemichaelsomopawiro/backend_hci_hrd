<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProgramFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'category',
        'description',
        'uploaded_by',
        'fileable_type',
        'fileable_id',
        'status',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer'
    ];

    // Relasi polymorphic - bisa attached ke Program, Episode, atau Schedule
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relasi dengan User (Uploader)
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scope untuk kategori file
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Scope untuk status file
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk file type
    public function scopeByFileType($query, string $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    // Method untuk mendapatkan URL file
    public function getFileUrlAttribute(): string
    {
        return url('storage/' . $this->file_path);
    }

    // Method untuk mendapatkan file size dalam format yang readable
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Method untuk check apakah file adalah image
    public function isImage(): bool
    {
        return in_array($this->mime_type, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ]);
    }

    // Method untuk check apakah file adalah video
    public function isVideo(): bool
    {
        return in_array($this->mime_type, [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm'
        ]);
    }

    // Method untuk check apakah file adalah document
    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ]);
    }

    // Method untuk mendapatkan icon berdasarkan file type
    public function getFileIconAttribute(): string
    {
        if ($this->isImage()) {
            return 'image';
        } elseif ($this->isVideo()) {
            return 'video';
        } elseif ($this->isDocument()) {
            return 'document';
        } else {
            return 'file';
        }
    }
}

