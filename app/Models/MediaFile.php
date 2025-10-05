<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'file_url',
        'mime_type',
        'file_size',
        'file_type',
        'program_id',
        'episode_id',
        'uploaded_by',
        'description',
        'metadata',
        'is_processed',
        'is_public',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'array',
        'is_processed' => 'boolean',
        'is_public' => 'boolean',
    ];

    // Relasi dengan Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Relasi dengan Episode
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    // Relasi dengan User (uploaded by)
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scope untuk file berdasarkan tipe
    public function scopeByType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    // Scope untuk file yang sudah diproses
    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    // Scope untuk file publik
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // Method untuk mendapatkan URL file
    public function getUrlAttribute()
    {
        return $this->file_url ?: asset('storage/' . $this->file_path);
    }

    // Method untuk mendapatkan ukuran file yang readable
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Method untuk mengecek apakah file adalah gambar
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // Method untuk mengecek apakah file adalah video
    public function isVideo()
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    // Method untuk mengecek apakah file adalah audio
    public function isAudio()
    {
        return str_starts_with($this->mime_type, 'audio/');
    }
}
