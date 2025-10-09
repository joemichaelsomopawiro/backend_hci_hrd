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
        'joined_at' => 'date',
        'left_at' => 'date'
    ];

    /**
     * Relasi dengan Production Team
     */
    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    /**
     * Relasi dengan User
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
        return ProductionTeam::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /**
     * Scope: Active members only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}

