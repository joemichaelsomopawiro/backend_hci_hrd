<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'role',
        'program_id',
        'team_lead_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relasi dengan Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Relasi dengan User (Team Lead)
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    // Relasi dengan TeamMember
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    // Relasi dengan User melalui TeamMember (Many-to-Many)
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
                    ->withPivot(['role', 'is_active', 'joined_at', 'left_at', 'notes'])
                    ->withTimestamps();
    }

    // Relasi dengan Schedule
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    // Relasi dengan Program (Many-to-Many)
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_team');
    }

    // Scope untuk tim aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk tim berdasarkan role
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Method untuk mendapatkan jumlah anggota tim
    public function getMemberCountAttribute()
    {
        return $this->members()->where('is_active', true)->count();
    }

    // Method untuk mendapatkan anggota aktif
    public function getActiveMembersAttribute()
    {
        return $this->members()->where('is_active', true)->get();
    }
}
