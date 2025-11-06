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
        'user_id',
        'role',
        'is_active',
        'joined_at',
        'left_at',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
            'kreatif' => 'Kreatif',
            'musik_arr' => 'Musik Arranger',
            'sound_eng' => 'Sound Engineer',
            'produksi' => 'Produksi',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Design'
        ];

        return $labels[$this->role] ?? $this->role;
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
}