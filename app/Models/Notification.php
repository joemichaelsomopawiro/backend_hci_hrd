<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'status',
        'episode_id',
        'program_id',
        'related_type',
        'related_id',
        'priority',
        'read_at',
        'archived_at',
        'scheduled_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'scheduled_at' => 'datetime'
    ];

    /**
     * Relationship dengan User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Polymorphic relationship dengan related object
     */
    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now()
        ]);
    }

    /**
     * Mark notification as archived
     */
    public function markAsArchived(): void
    {
        $this->update([
            'status' => 'archived',
            'archived_at' => now()
        ]);
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    /**
     * Check if notification is archived
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];

        return $labels[$this->priority] ?? $this->priority;
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute(): string
    {
        $colors = [
            'low' => 'green',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red'
        ];

        return $colors[$this->priority] ?? 'blue';
    }

    /**
     * Scope untuk notification yang unread
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Scope untuk notification yang read
     */
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    /**
     * Scope untuk notification yang archived
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope berdasarkan priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope berdasarkan type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope untuk notification yang urgent
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Scope untuk notification yang high priority
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }
}














