<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'role',
        'program_id',
        'team_lead_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relasi dengan Program (Legacy - untuk backward compatibility jika ada teams yang dibuat langsung dengan program_id)
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Relasi dengan Programs (Many-to-Many through program_team pivot table)
    // Satu team bisa di-assign ke banyak programs
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_team')
            ->withTimestamps();
    }

    // Relasi dengan User (Team Lead)
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    // Relasi dengan User (Created By)
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi many-to-many dengan Users (Team Members)
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['role', 'joined_at', 'is_active', 'left_at', 'notes'])
            ->withTimestamps();
    }

    // Relasi dengan Schedules
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    // Scope untuk team aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Method untuk menambah member ke team
    public function addMember(User $user, string $role = 'member', bool $isActive = true): void
    {
        $this->members()->attach($user->id, [
            'role' => $role,
            'is_active' => $isActive,
            'joined_at' => now()
        ]);
    }

    // Method untuk menghapus member dari team
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    // Method untuk update role member
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'role' => $role,
            'updated_at' => now()
        ]);
    }

    // Method untuk mendapatkan members berdasarkan role
    public function getMembersByRole(string $role)
    {
        return $this->members()->wherePivot('role', $role)->get();
    }

    // Method untuk check apakah user adalah member
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    // Method untuk check apakah user adalah team lead
    public function isTeamLead(User $user): bool
    {
        return $this->team_lead_id === $user->id;
    }
}