<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MusicSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_arranger_id',
        'song_id',
        'proposed_singer_id',
        'approved_singer_id',
        'arrangement_notes',
        'requested_date',
        'current_state',
        'submission_status',
        'status',
        'producer_notes',
        'producer_feedback',
        'arrangement_file_path',
        'arrangement_file_url',
        'processed_audio_path',
        'processed_audio_url',
        'sound_engineering_notes',
        'quality_control_notes',
        'quality_control_approved',
        'final_approval_notes',
        'script_content',
        'storyboard_data',
        'recording_schedule',
        'shooting_schedule',
        'shooting_location',
        'budget_data',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'version',
        'parent_submission_id',
        'arrangement_started',
        'arrangement_started_at',
        'arrangement_completed_at',
        'arrangement_file_name',
        'assigned_sound_engineer_id',
        'sound_engineering_started_at',
        'sound_engineering_completed_at',
        'sound_engineering_rejected_at',
        'sound_engineer_feedback',
        'assigned_creative_id',
        'creative_work_started_at',
        'creative_work_completed_at',
        'qc_decision',
        'quality_score',
        'improvement_areas',
        'qc_completed_at',
        'processing_notes',
        'processed_at',
        'modified_by_producer',
        'modified_at'
    ];

    protected $casts = [
        'requested_date' => 'date',
        'recording_schedule' => 'datetime',
        'shooting_schedule' => 'datetime',
        'storyboard_data' => 'array',
        'budget_data' => 'array',
        'quality_control_approved' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'arrangement_started' => 'boolean',
        'arrangement_started_at' => 'datetime',
        'arrangement_completed_at' => 'datetime',
        'modified_at' => 'datetime'
    ];

    /**
     * Relationship dengan Music Arranger
     */
    public function musicArranger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'music_arranger_id');
    }

    /**
     * Relationship dengan Song
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /**
     * Relationship dengan Proposed Singer
     */
    public function proposedSinger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_singer_id');
    }

    /**
     * Relationship dengan Approved Singer
     */
    public function approvedSinger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_singer_id');
    }

    /**
     * Relationship dengan Producer yang melakukan modify
     */
    public function modifiedByProducer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by_producer');
    }

    /**
     * Relationship dengan Workflow States
     */
    public function workflowStates(): HasMany
    {
        return $this->hasMany(MusicWorkflowState::class, 'submission_id');
    }

    /**
     * Relationship dengan Current Workflow State
     */
    public function currentWorkflowState(): HasOne
    {
        return $this->hasOne(MusicWorkflowState::class, 'submission_id')
            ->where('current_state', $this->current_state)
            ->latest();
    }

    /**
     * Relationship dengan Workflow History
     */
    public function workflowHistory(): HasMany
    {
        return $this->hasMany(MusicWorkflowHistory::class, 'submission_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Relationship dengan Notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(MusicWorkflowNotification::class, 'submission_id');
    }

    /**
     * Scope untuk submissions berdasarkan state
     */
    public function scopeByState($query, $state)
    {
        return $query->where('current_state', $state);
    }

    /**
     * Scope untuk submissions berdasarkan Music Arranger
     */
    public function scopeByMusicArranger($query, $musicArrangerId)
    {
        return $query->where('music_arranger_id', $musicArrangerId);
    }

    /**
     * Scope untuk submissions berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        $roleStates = match($role) {
            'Music Arranger' => ['submitted', 'arranging'],
            'Producer' => ['producer_review', 'arrangement_review', 'quality_control', 'creative_review', 'producer_final_review', 'final_approval'],
            'Sound Engineer' => ['sound_engineering', 'sound_engineering_final'],
            'Creative' => ['creative_work'],
            'Manager Program' => ['manager_approval'],
            'General Affairs' => ['general_affairs'],
            'Promotion' => ['promotion'],
            'Production' => ['production'],
            default => []
        };
        
        return $query->whereIn('current_state', $roleStates);
    }

    /**
     * Scope untuk pending submissions
     */
    public function scopePending($query)
    {
        return $query->whereIn('current_state', [
            'submitted', 'producer_review', 'arranging', 'arrangement_review',
            'sound_engineering', 'quality_control', 'creative_work'
        ]);
    }

    /**
     * Scope untuk urgent submissions
     */
    public function scopeUrgent($query)
    {
        return $query->where('requested_date', '<=', now()->addDays(2))
            ->whereIn('current_state', ['submitted', 'producer_review', 'arranging']);
    }

    /**
     * Check if submission can be transitioned to new state
     */
    public function canTransitionTo($newState): bool
    {
        $validTransitions = [
            'submitted' => ['producer_review', 'rejected'],
            'producer_review' => ['arranging', 'rejected'], // Producer terima/tolak usulan lagu+penyanyi
            'arranging' => ['arrangement_review', 'rejected'], // Music Arranger selesaikan arrange
            'arrangement_review' => ['sound_engineering', 'quality_control', 'rejected'], // Producer terima/tolak arrangement
            'sound_engineering' => ['quality_control', 'rejected'], // Sound Engineer selesaikan pekerjaan
            'quality_control' => ['creative_work', 'rejected'], // Producer quality control
            'creative_work' => ['creative_review', 'rejected'], // Creative selesaikan script, storyboard, jadwal, budget
            'creative_review' => ['producer_final_review', 'creative_work', 'rejected'], // Producer review creative work
            'producer_final_review' => ['manager_approval', 'creative_work', 'rejected'], // Producer final review
            'manager_approval' => ['general_affairs', 'producer_final_review', 'rejected'], // Manager approval
            'general_affairs' => ['promotion', 'production', 'sound_engineering_final', 'rejected'], // General affairs release funds
            'promotion' => ['final_approval', 'rejected'], // Promotion completed
            'production' => ['final_approval', 'rejected'], // Production completed
            'sound_engineering_final' => ['final_approval', 'rejected'], // Sound engineering final completed
            'final_approval' => ['completed', 'rejected'], // Producer final approval
            'completed' => [],
            'rejected' => ['arranging', 'creative_work', 'producer_final_review'] // Jika ditolak, bisa kembali ke tahap sebelumnya
        ];

        return in_array($newState, $validTransitions[$this->current_state] ?? []);
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->current_state) {
            'submitted' => 'blue',
            'producer_review' => 'yellow',
            'arranging' => 'purple',
            'arrangement_review' => 'yellow',
            'sound_engineering' => 'indigo',
            'quality_control' => 'orange',
            'creative_work' => 'pink',
            'final_approval' => 'yellow',
            'completed' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get status label for UI
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->current_state) {
            'submitted' => 'Submitted',
            'producer_review' => 'Producer Review',
            'arranging' => 'Arranging',
            'arrangement_review' => 'Arrangement Review',
            'sound_engineering' => 'Sound Engineering',
            'quality_control' => 'Quality Control',
            'creative_work' => 'Creative Work',
            'final_approval' => 'Final Approval',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            default => 'Unknown'
        };
    }

    /**
     * Get priority level
     */
    public function getPriorityAttribute(): string
    {
        if ($this->requested_date && $this->requested_date <= now()->addDays(1)) {
            return 'urgent';
        } elseif ($this->requested_date && $this->requested_date <= now()->addDays(3)) {
            return 'high';
        } elseif ($this->requested_date && $this->requested_date <= now()->addDays(7)) {
            return 'medium';
        }
        return 'low';
    }
}
