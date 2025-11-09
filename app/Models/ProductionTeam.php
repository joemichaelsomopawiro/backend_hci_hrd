<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'producer_id',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Relationship dengan Producer
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan Team Members
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProductionTeamMember::class);
    }

    /**
     * Relationship dengan Programs
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * Check if team has all required roles
     */
    public function hasAllRequiredRoles(): bool
    {
        $requiredRoles = ['kreatif', 'musik_arr', 'sound_eng', 'produksi', 'editor', 'art_set_design'];
        $existingRoles = $this->members()->where('is_active', true)->pluck('role')->toArray();
        
        return count(array_intersect($requiredRoles, $existingRoles)) === count($requiredRoles);
    }

    /**
     * Get missing roles
     */
    public function getMissingRoles(): array
    {
        $requiredRoles = ['kreatif', 'musik_arr', 'sound_eng', 'produksi', 'editor', 'art_set_design'];
        $existingRoles = $this->members()->where('is_active', true)->pluck('role')->toArray();
        
        return array_diff($requiredRoles, $existingRoles);
    }

    /**
     * Check if team is ready for production
     */
    public function isReadyForProduction(): bool
    {
        return $this->is_active && $this->hasAllRequiredRoles();
    }

    /**
     * Get roles summary
     */
    public function getRolesSummary(): array
    {
        $members = $this->members()->where('is_active', true)->get();
        $summary = [];
        
        foreach ($members as $member) {
            $summary[$member->role] = [
                'user_id' => $member->user_id,
                'user_name' => $member->user->name ?? 'Unknown',
                'joined_at' => $member->joined_at
            ];
        }
        
        return $summary;
    }

    /**
     * Add member to team
     */
    public function addMember(int $userId, string $role, ?string $notes = null): ProductionTeamMember
    {
        return $this->members()->create([
            'user_id' => $userId,
            'role' => $role,
            'notes' => $notes,
            'joined_at' => now()
        ]);
    }

    /**
     * Remove member from team
     */
    public function removeMember(int $userId, string $role): bool
    {
        $member = $this->members()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->where('is_active', true)
            ->first();
            
        if (!$member) return false;
        
        // Check if this is the last member for this role
        $roleCount = $this->members()
            ->where('role', $role)
            ->where('is_active', true)
            ->count();
            
        if ($roleCount <= 1) {
            throw new \Exception("Cannot remove the last member for role: {$role}");
        }
        
        return $member->update([
            'is_active' => false,
            'left_at' => now()
        ]);
    }

    /**
     * Scope untuk team yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk team yang ready for production
     */
    public function scopeReadyForProduction($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope berdasarkan producer
     */
    public function scopeByProducer($query, $producerId)
    {
        return $query->where('producer_id', $producerId);
    }
}