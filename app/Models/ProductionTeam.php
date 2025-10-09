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

    // 6 Role wajib yang harus ada di setiap tim
    const REQUIRED_ROLES = [
        'kreatif',
        'musik_arr',
        'sound_eng',
        'produksi',
        'editor',
        'art_set_design'
    ];

    const ROLE_LABELS = [
        'kreatif' => 'Kreatif',
        'musik_arr' => 'Musik Arranger',
        'sound_eng' => 'Sound Engineer',
        'produksi' => 'Produksi',
        'editor' => 'Editor',
        'art_set_design' => 'Art & Set Design'
    ];

    /**
     * Relasi dengan User (Producer sebagai Team Leader)
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * Relasi dengan User (Created By)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi dengan Production Team Members
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProductionTeamMember::class);
    }

    /**
     * Relasi dengan Program Regular
     */
    public function programs(): HasMany
    {
        return $this->hasMany(ProgramRegular::class);
    }

    /**
     * Get active members only
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    /**
     * Get members by specific role
     */
    public function membersByRole(string $role)
    {
        return $this->members()
            ->where('role', $role)
            ->where('is_active', true)
            ->with('user')
            ->get();
    }

    /**
     * Check if team has all required roles (minimum 1 person per role)
     */
    public function hasAllRequiredRoles(): bool
    {
        $existingRoles = $this->activeMembers()
            ->pluck('role')
            ->unique()
            ->toArray();

        foreach (self::REQUIRED_ROLES as $role) {
            if (!in_array($role, $existingRoles)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing roles (roles that don't have any member yet)
     */
    public function getMissingRoles(): array
    {
        $existingRoles = $this->activeMembers()
            ->pluck('role')
            ->unique()
            ->toArray();

        return array_diff(self::REQUIRED_ROLES, $existingRoles);
    }

    /**
     * Check if team is ready for production (has all roles + producer)
     */
    public function isReadyForProduction(): bool
    {
        return $this->is_active && 
               $this->producer_id !== null && 
               $this->hasAllRequiredRoles();
    }

    /**
     * Get team summary (roles count)
     */
    public function getRolesSummary(): array
    {
        $summary = [];
        
        foreach (self::REQUIRED_ROLES as $role) {
            $count = $this->activeMembers()
                ->where('role', $role)
                ->count();
            
            $summary[$role] = [
                'label' => self::ROLE_LABELS[$role],
                'count' => $count,
                'has_member' => $count > 0
            ];
        }

        return $summary;
    }

    /**
     * Add member to team with role
     */
    public function addMember(int $userId, string $role, ?string $notes = null): ProductionTeamMember
    {
        return $this->members()->create([
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => now(),
            'notes' => $notes,
            'is_active' => true
        ]);
    }

    /**
     * Remove member from team
     */
    public function removeMember(int $userId, string $role): bool
    {
        return $this->members()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->update([
                'is_active' => false,
                'left_at' => now()
            ]);
    }

    /**
     * Scope: Active teams only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ready for production (has all required roles)
     */
    public function scopeReadyForProduction($query)
    {
        return $query->where('is_active', true)
            ->whereHas('members', function ($q) {
                $q->where('is_active', true);
            });
    }

    /**
     * Scope: Teams by producer
     */
    public function scopeByProducer($query, int $producerId)
    {
        return $query->where('producer_id', $producerId);
    }
}

