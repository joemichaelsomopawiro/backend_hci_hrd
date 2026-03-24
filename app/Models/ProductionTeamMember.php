<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_team_id',
        'assignment_id',
        'user_id',
        'role',
        'is_active',
        'joined_at',
        'left_at',
        'notes',
        'role_notes',
        'status',
        'is_coordinator'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_coordinator' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime'
    ];

    /**
     * Relationship dengan Production Team
     */
    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    /**
     * Relationship dengan Production Team Assignment
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductionTeamAssignment::class, 'assignment_id');
    }

    /**
     * Relationship dengan User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'producer' => 'Producer',
            'creative' => 'Creative',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'production' => 'Production',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Properti',
            'kreatif' => 'Creative',
            'produksi' => 'Production',
            'sound_engineer' => 'Sound Engineer',
            'music_arranger' => 'Music Arranger'
        ];

        if (isset($labels[$this->role])) {
            return $labels[$this->role];
        }

        // Return capitalized role if not in list
        return ucwords(str_replace(['_', '-'], ' ', $this->role));
    }

    /**
     * Scope untuk member yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope berdasarkan team
     */
    public function scopeByTeam($query, $teamId)
    {
        return $query->where('production_team_id', $teamId);
    }

    /**
     * Static helper to check if a user is a member of specific team type(s) for an episode
     */
    public static function isMemberForEpisode(int $userId, int $episodeId, $teamTypes = []): bool
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) use ($episodeId, $teamTypes) {
                $q->where('episode_id', $episodeId)
                    ->where('status', '!=', 'cancelled');
                
                if (!empty($teamTypes)) {
                    if (is_array($teamTypes)) {
                        $q->whereIn('team_type', (array)$teamTypes);
                    } else {
                        $q->where('team_type', $teamTypes);
                    }
                }
            })->exists();
    }

    /**
     * Static helper to check if a user is a coordinator for an episode
     */
    public static function isCoordinatorForEpisode(int $userId, int $episodeId, $teamTypes = []): bool
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_coordinator', true)
            ->whereHas('assignment', function ($q) use ($episodeId, $teamTypes) {
                $q->where('episode_id', $episodeId)
                    ->where('status', '!=', 'cancelled');
                
                if (!empty($teamTypes)) {
                    if (is_array($teamTypes)) {
                        $q->whereIn('team_type', (array)$teamTypes);
                    } else {
                        $q->where('team_type', $teamTypes);
                    }
                }
            })->exists();
    }
}