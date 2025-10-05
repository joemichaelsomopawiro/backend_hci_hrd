<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicWorkflowHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'from_state',
        'to_state',
        'action_by_user_id',
        'action_notes'
    ];

    protected $table = 'music_workflow_histories';

    /**
     * Relationship dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class, 'submission_id');
    }

    /**
     * Relationship dengan Action By User
     */
    public function actionByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }

    /**
     * Scope untuk history berdasarkan submission
     */
    public function scopeBySubmission($query, $submissionId)
    {
        return $query->where('submission_id', $submissionId);
    }

    /**
     * Scope untuk history berdasarkan user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('action_by_user_id', $userId);
    }

    /**
     * Scope untuk history berdasarkan state transition
     */
    public function scopeByTransition($query, $fromState, $toState)
    {
        return $query->where('from_state', $fromState)
            ->where('to_state', $toState);
    }

    /**
     * Get transition label for UI
     */
    public function getTransitionLabelAttribute(): string
    {
        if ($this->from_state && $this->to_state) {
            return ucfirst(str_replace('_', ' ', $this->from_state)) . ' → ' . ucfirst(str_replace('_', ' ', $this->to_state));
        } elseif ($this->to_state) {
            return 'Started: ' . ucfirst(str_replace('_', ' ', $this->to_state));
        }
        return 'Unknown transition';
    }

    /**
     * Get action description
     */
    public function getActionDescriptionAttribute(): string
    {
        $userName = $this->actionByUser->name ?? 'Unknown User';
        $transition = $this->transition_label;
        
        if ($this->action_notes) {
            return "{$userName} moved workflow from {$transition}. Note: {$this->action_notes}";
        }
        
        return "{$userName} moved workflow from {$transition}";
    }
}