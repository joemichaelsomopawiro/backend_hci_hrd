<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'audio_file_path',
        'audio_file_name',
        'file_size',
        'mime_type',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'bpm' => 'integer',
        'file_size' => 'integer'
    ];

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang update
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship dengan Music Arrangements
     */
    public function musicArrangements(): HasMany
    {
        return $this->hasMany(MusicArrangement::class);
    }

    /**
     * Scope untuk songs yang available
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
