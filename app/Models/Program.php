<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'type',
        'start_date',
        'end_date',
        'air_time',
        'duration_minutes',
        'broadcast_channel',
        'rundown',
        'requirements',
        'manager_id',
        'producer_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'air_time' => 'datetime:H:i',
        'requirements' => 'array',
    ];

    // Relasi dengan User (Manager Program)
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // Relasi dengan User (Producer)
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    // Relasi dengan Episode
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    // Relasi dengan Team (Many-to-Many)
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'program_team');
    }

    // Relasi dengan Schedule
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    // Relasi dengan MediaFile
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    // Relasi dengan ProductionEquipment
    public function productionEquipment(): HasMany
    {
        return $this->hasMany(ProductionEquipment::class);
    }

    // Relasi dengan ProgramNotification
    public function notifications(): HasMany
    {
        return $this->hasMany(ProgramNotification::class);
    }

    // Scope untuk program aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope untuk program berdasarkan tipe
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Method untuk mendapatkan status program
    public function getStatusAttribute($value)
    {
        return ucfirst($value);
    }

    // Method untuk mendapatkan durasi dalam format yang lebih readable
    public function getDurationFormattedAttribute()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        
        return $minutes . 'm';
    }
}
