<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'genre',
        'lyrics',
        'duration',
        'key_signature',
        'bpm',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'audio_file_path',
        'audio_file_name',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'bpm' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * Relationship dengan User yang membuat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang mengupdate
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship dengan MusicSubmission
     */
    public function musicSubmissions()
    {
        return $this->hasMany(MusicSubmission::class);
    }

    /**
     * Scope untuk lagu yang tersedia
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope untuk pencarian lagu
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('artist', 'like', "%{$search}%")
              ->orWhere('genre', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute()
    {
        return $this->duration ?? 'N/A';
    }

    /**
     * Check if song is available for arrangement
     */
    public function isAvailable()
    {
        return $this->status === 'available';
    }

    /**
     * Get audio file URL
     */
    public function getAudioUrlAttribute()
    {
        if ($this->audio_file_path) {
            return asset('storage/' . $this->audio_file_path);
        }
        return null;
    }
}