<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionTeamAssignment extends Model
{
    use HasFactory;

    protected $table = 'production_teams_assignment';

    protected $fillable = [
        'music_submission_id',
        'schedule_id',
        'assigned_by',
        'team_type',
        'team_name',
        'team_notes',
        'status',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function musicSubmission()
    {
        return $this->belongsTo(MusicSubmission::class, 'music_submission_id');
    }

    public function schedule()
    {
        return $this->belongsTo(MusicSchedule::class, 'schedule_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function members()
    {
        return $this->hasMany(ProductionTeamMember::class, 'assignment_id');
    }

    /**
     * Scopes
     */
    public function scopeShooting($query)
    {
        return $query->where('team_type', 'shooting');
    }

    public function scopeSetting($query)
    {
        return $query->where('team_type', 'setting');
    }

    public function scopeRecording($query)
    {
        return $query->where('team_type', 'recording');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Helpers
     */
    public function isAssigned()
    {
        return $this->status === 'assigned';
    }

    public function isConfirmed()
    {
        return $this->status === 'confirmed';
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

    public function getMembersCount()
    {
        return $this->members()->count();
    }

    public function getTeamTypeLabel()
    {
        $labels = [
            'shooting' => 'Tim Syuting',
            'setting' => 'Tim Setting (Art & Set)',
            'recording' => 'Tim Rekam Vokal',
        ];

        return $labels[$this->team_type] ?? $this->team_type;
    }

    public function getStatusLabel()
    {
        $labels = [
            'assigned' => 'Assigned',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'assigned' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
        ];

        return $colors[$this->status] ?? 'gray';
    }
}






