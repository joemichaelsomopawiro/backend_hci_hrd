<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MusicSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'music_submissions';

    protected $fillable = [
        'music_arranger_id',
        'song_id',
        'proposed_singer_id',
        'arrangement_notes',
        'requested_date',
        'submission_status',
        'current_state',
        'episode_id',
        'program_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_notes',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Relationships
    public function musicArranger()
    {
        return $this->belongsTo(User::class, 'music_arranger_id');
    }

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function singer()
    {
        return $this->belongsTo(Singer::class, 'proposed_singer_id');
    }

    public function episode()
    {
        return $this->belongsTo(Episode::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function workflowStates()
    {
        return $this->hasMany(MusicWorkflowState::class, 'submission_id');
    }

    public function workflowHistory()
    {
        return $this->hasMany(MusicWorkflowHistory::class, 'submission_id');
    }

    public function notifications()
    {
        return $this->hasMany(MusicWorkflowNotification::class, 'submission_id');
    }

    public function schedules()
    {
        return $this->hasMany(MusicSchedule::class, 'music_submission_id');
    }

    public function budgetApprovals()
    {
        return $this->hasMany(BudgetApproval::class, 'music_submission_id');
    }

    public function artSetProperties()
    {
        return $this->hasMany(ArtSetProperty::class);
    }

    public function productionTeamAssignments()
    {
        return $this->hasMany(ProductionTeamAssignment::class, 'music_submission_id');
    }
}
