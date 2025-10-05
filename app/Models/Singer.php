<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Singer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'bio',
        'profile_picture',
        'specialties',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specialties' => 'array',
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
     * Relationship dengan MusicSubmission sebagai proposed singer
     */
    public function proposedSubmissions()
    {
        return $this->hasMany(MusicSubmission::class, 'proposed_singer_id');
    }

    /**
     * Relationship dengan MusicSubmission sebagai approved singer
     */
    public function approvedSubmissions()
    {
        return $this->hasMany(MusicSubmission::class, 'approved_singer_id');
    }

    /**
     * Scope untuk singer yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope untuk pencarian singer
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Get profile picture URL
     */
    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            return 'http://localhost:8000/storage/' . $this->profile_picture;
        }
        return null;
    }

    /**
     * Get specialties as string
     */
    public function getSpecialtiesStringAttribute()
    {
        return $this->specialties ? implode(', ', $this->specialties) : 'N/A';
    }
}


