<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'episode_number',
        'program_id',
        'air_date',
        'production_date',
        'status',
        'script',
        'talent_data',
        'location',
        'notes',
        'production_notes',
    ];

    protected $casts = [
        'air_date' => 'date',
        'production_date' => 'date',
        'talent_data' => 'array',
        'production_notes' => 'array',
    ];

    // Relasi dengan Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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

    // Scope untuk episode berdasarkan status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk episode yang akan tayang
    public function scopeUpcoming($query)
    {
        return $query->where('air_date', '>=', now()->toDateString())
                    ->where('status', '!=', 'aired');
    }

    // Scope untuk episode yang sudah tayang
    public function scopeAired($query)
    {
        return $query->where('status', 'aired');
    }

    // Method untuk mendapatkan status episode
    public function getStatusAttribute($value)
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    // Method untuk mendapatkan durasi sampai tayang
    public function getDaysUntilAirAttribute()
    {
        return now()->diffInDays($this->air_date, false);
    }

    // Method untuk mengecek apakah episode sudah siap tayang
    public function isReadyToAir()
    {
        return $this->status === 'ready_to_air' && 
               $this->air_date <= now()->addDays(1)->toDateString();
    }

    // Method untuk mendapatkan progress episode
    public function getProgressAttribute()
    {
        $statuses = ['draft', 'in_production', 'post_production', 'ready_to_air', 'aired'];
        $currentIndex = array_search($this->status, $statuses);
        
        if ($currentIndex === false) {
            return 0;
        }
        
        return round(($currentIndex + 1) / count($statuses) * 100);
    }
}
