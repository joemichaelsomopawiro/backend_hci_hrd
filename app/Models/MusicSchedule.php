<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_submission_id',
        'created_by',
        'schedule_type',
        'scheduled_datetime',
        'location',
        'location_address',
        'schedule_notes',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'rescheduled_datetime',
        'reschedule_reason',
        'rescheduled_by',
        'rescheduled_at',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'scheduled_datetime' => 'datetime',
        'rescheduled_datetime' => 'datetime',
        'cancelled_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function musicSubmission()
    {
        return $this->belongsTo(MusicSubmission::class, 'music_submission_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rescheduler()
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function teamAssignments()
    {
        return $this->hasMany(ProductionTeamAssignment::class, 'schedule_id');
    }

    /**
     * Scopes
     */
    public function scopeRecording($query)
    {
        return $query->where('schedule_type', 'recording');
    }

    public function scopeShooting($query)
    {
        return $query->where('schedule_type', 'shooting');
    }

    public function scopeScheduled($query)
    {
        return $query->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_datetime', '>=', now())
                     ->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopePast($query)
    {
        return $query->where('scheduled_datetime', '<', now())
                     ->whereIn('status', ['completed', 'cancelled']);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Helpers
     */
    public function isScheduled()
    {
        return in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function isRescheduled()
    {
        return $this->status === 'rescheduled' || $this->rescheduled_datetime !== null;
    }

    public function isUpcoming()
    {
        return $this->scheduled_datetime >= now() && $this->isScheduled();
    }

    public function isPast()
    {
        return $this->scheduled_datetime < now();
    }

    public function getEffectiveDatetime()
    {
        return $this->rescheduled_datetime ?? $this->scheduled_datetime;
    }

    public function getScheduleTypeLabel()
    {
        $labels = [
            'recording' => 'Rekaman Vokal',
            'shooting' => 'Syuting Video Klip',
        ];

        return $labels[$this->schedule_type] ?? $this->schedule_type;
    }

    public function getStatusLabel()
    {
        $labels = [
            'scheduled' => 'Scheduled',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'rescheduled' => 'Rescheduled',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'scheduled' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            'rescheduled' => 'orange',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getDatetimeFormatted($format = 'd M Y, H:i')
    {
        return $this->getEffectiveDatetime()->format($format);
    }
}






