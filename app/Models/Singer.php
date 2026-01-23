<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Singer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stage_name',
        'bio',
        'photo_url',
        'genre',
        'social_media',
        'is_active'
    ];

    protected $casts = [
        'social_media' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship dengan Music Arrangements
     */
    public function musicArrangements(): HasMany
    {
        return $this->hasMany(MusicArrangement::class);
    }

    /**
     * Scope untuk singer yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk singer berdasarkan genre
     */
    public function scopeByGenre($query, $genre)
    {
        return $query->where('genre', $genre);
    }

    /**
     * Get display name (stage_name jika ada, otherwise name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->stage_name ?? $this->name;
    }

    /**
     * Check if singer has arrangements
     */
    public function hasArrangements(): bool
    {
        return $this->musicArrangements()->exists();
    }
}
