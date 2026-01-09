<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'current_state',
        'assigned_to_role',
        'assigned_to_user_id',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang assigned
     */
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get state label
     */
    public function getStateLabelAttribute(): string
    {
        $labels = [
            'program_created' => 'Program Created',
            'episode_generated' => 'Episode Generated',
            'music_arrangement' => 'Music Arrangement',
            'creative_work' => 'Creative Work',
            'production_planning' => 'Production Planning',
            'equipment_request' => 'Equipment Request',
            'shooting_recording' => 'Shooting & Recording',
            'editing' => 'Editing',
            'quality_control' => 'Quality Control',
            'broadcasting' => 'Broadcasting',
            'promotion' => 'Promotion',
            'completed' => 'Completed'
        ];

        return $labels[$this->current_state] ?? $this->current_state;
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'creative' => 'Creative',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'production' => 'Production',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Properti',
            'graphic_design' => 'Graphic Design',
            'promotion' => 'Promotion',
            'broadcasting' => 'Broadcasting',
            'quality_control' => 'Quality Control'
        ];

        return $labels[$this->assigned_to_role] ?? $this->assigned_to_role;
    }

    /**
     * Scope berdasarkan state
     */
    public function scopeByState($query, $state)
    {
        return $query->where('current_state', $state);
    }

    /**
     * Scope berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('assigned_to_role', $role);
    }

    /**
     * Scope berdasarkan user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('assigned_to_user_id', $userId);
    }
}














