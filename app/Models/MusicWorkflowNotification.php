<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicWorkflowNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'user_id',
        'notification_type',
        'title',
        'message',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    /**
     * Relationship dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class, 'submission_id');
    }

    /**
     * Relationship dengan User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope untuk notifications berdasarkan user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope untuk read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope untuk notifications berdasarkan type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope untuk notifications berdasarkan submission
     */
    public function scopeBySubmission($query, $submissionId)
    {
        return $query->where('submission_id', $submissionId);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Get notification icon
     */
    public function getIconAttribute(): string
    {
        return match($this->notification_type) {
            'submission_received' => '📥',
            'arrangement_request' => '🎵',
            'arrangement_approved' => '✅',
            'arrangement_rejected' => '❌',
            'sound_engineering_request' => '🎛️',
            'quality_control_request' => '🔍',
            'creative_work_request' => '🎨',
            'final_approval_request' => '👑',
            'workflow_completed' => '🎉',
            'workflow_rejected' => '🚫',
            default => '📢'
        };
    }

    /**
     * Get notification color
     */
    public function getColorAttribute(): string
    {
        return match($this->notification_type) {
            'submission_received' => 'blue',
            'arrangement_request' => 'purple',
            'arrangement_approved' => 'green',
            'arrangement_rejected' => 'red',
            'sound_engineering_request' => 'indigo',
            'quality_control_request' => 'orange',
            'creative_work_request' => 'pink',
            'final_approval_request' => 'yellow',
            'workflow_completed' => 'green',
            'workflow_rejected' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get time ago string
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get workflow data for notification
     */
    public function getWorkflowDataAttribute(): array
    {
        $submission = $this->submission;
        
        if (!$submission) {
            return [];
        }

        return [
            'song_title' => $submission->song->title ?? 'Unknown Song',
            'music_arranger_name' => $submission->musicArranger->name ?? 'Unknown Arranger',
            'current_state' => $submission->current_state,
            'proposed_singer_name' => $submission->proposedSinger->name ?? null,
            'approved_singer_name' => $submission->approvedSinger->name ?? null,
            'arrangement_notes' => $submission->arrangement_notes,
            'producer_notes' => $submission->producer_notes,
            'requested_date' => $submission->requested_date?->format('Y-m-d'),
            'created_at' => $submission->created_at->format('Y-m-d H:i:s')
        ];
    }
}