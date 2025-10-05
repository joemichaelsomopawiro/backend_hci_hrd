<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'is_active',
        'joined_at',
        'left_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    // Relasi dengan Team
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Relasi dengan User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scope untuk member aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk member berdasarkan role
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Scope untuk member yang masih aktif di tim
    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
                    ->whereNull('left_at');
    }

    // Method untuk mendapatkan role member
    public function getRoleAttribute($value)
    {
        return ucfirst($value);
    }

    // Method untuk mengecek apakah member adalah team lead
    public function isTeamLead()
    {
        return $this->role === 'lead';
    }

    // Method untuk mengecek apakah member adalah assistant
    public function isAssistant()
    {
        return $this->role === 'assistant';
    }

    // Method untuk mendapatkan durasi keanggotaan
    public function getMembershipDurationAttribute()
    {
        $endDate = $this->left_at ?: now();
        return $this->joined_at->diffInDays($endDate);
    }

    // Method untuk mengecek apakah member masih aktif
    public function isActive()
    {
        return $this->is_active && is_null($this->left_at);
    }
}
