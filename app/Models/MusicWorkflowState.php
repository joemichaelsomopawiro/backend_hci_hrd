<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicWorkflowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'current_state',
        'assigned_to_role',
        'assigned_to_user_id',
        'notes'
    ];

    protected $casts = [
        'current_state' => 'string',
        'assigned_to_role' => 'string'
    ];

    /**
     * Relationship dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class, 'submission_id');
    }

    /**
     * Relationship dengan Assigned User
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Scope untuk state berdasarkan submission
     */
    public function scopeBySubmission($query, $submissionId)
    {
        return $query->where('submission_id', $submissionId);
    }

    /**
     * Scope untuk state berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('assigned_to_role', $role);
    }

    /**
     * Scope untuk state berdasarkan user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    /**
     * Get state color for UI
     */
    public function getStateColorAttribute(): string
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
     * Get state label for UI
     */
    public function getStateLabelAttribute(): string
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
}