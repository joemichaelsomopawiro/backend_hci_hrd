<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'program_id',
        'episode_id',
        'team_id',
        'assigned_to',
        'start_time',
        'end_time',
        'deadline',
        'status',
        'location',
        'notes',
        'is_recurring',
        'recurring_pattern',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'deadline' => 'datetime',
        'is_recurring' => 'boolean',
        'recurring_pattern' => 'array',
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

    // Relasi dengan Team
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Relasi dengan User (assigned to)
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scope untuk schedule berdasarkan status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk schedule berdasarkan tipe
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope untuk schedule yang akan datang
    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now());
    }

    // Scope untuk schedule yang sudah lewat
    public function scopePast($query)
    {
        return $query->where('end_time', '<', now());
    }

    // Scope untuk schedule hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('start_time', now()->toDateString());
    }

    // Scope untuk schedule minggu ini
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    // Method untuk mendapatkan status schedule
    public function getStatusAttribute($value)
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    // Method untuk mengecek apakah schedule sudah lewat
    public function isOverdue()
    {
        return $this->deadline && $this->deadline < now() && $this->status !== 'completed';
    }

    // Method untuk mendapatkan durasi schedule
    public function getDurationAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    // Method untuk mengecek apakah schedule sedang berlangsung
    public function isInProgress()
    {
        $now = now();
        return $now->between($this->start_time, $this->end_time);
    }

    // Method untuk mendapatkan progress schedule
    public function getProgressAttribute()
    {
        if ($this->status === 'completed') {
            return 100;
        }

        if ($this->status === 'cancelled') {
            return 0;
        }

        $now = now();
        $totalDuration = $this->start_time->diffInMinutes($this->end_time);
        $elapsed = $this->start_time->diffInMinutes($now);

        if ($elapsed <= 0) {
            return 0;
        }

        if ($elapsed >= $totalDuration) {
            return 100;
        }

        return round(($elapsed / $totalDuration) * 100);
    }
}
