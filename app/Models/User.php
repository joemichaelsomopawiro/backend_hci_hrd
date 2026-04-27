<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'phone_verified_at',
        'profile_picture',
        'employee_id',
        'role',
        'access_level',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'notification_preferences' => 'array',
    ];

    protected $appends = ['profile_picture_url']; // <-- Tambahkan baris ini

    public function isPhoneVerified()
    {
        return !is_null($this->phone_verified_at);
    }

    // Relasi dengan Teams
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['role', 'joined_at', 'is_active', 'left_at', 'notes'])
            ->withTimestamps();
    }

    // Relasi dengan Teams sebagai Team Lead
    public function ledTeams()
    {
        return $this->hasMany(Team::class, 'team_lead_id');
    }

    /**
     * Relationship dengan Production Team Members
     */
    public function productionTeamMembers()
    {
        return $this->hasMany(ProductionTeamMember::class, 'user_id');
    }

    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            // Gunakan asset() agar otomatis mengikuti APP_URL di .env
            return asset('storage/' . $this->profile_picture);
        }
        return null;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getEmployeeDataAttribute()
    {
        return $this->employee;
    }

    // Helper methods untuk role
    public function isHR()
    {
        return $this->role === 'HR';
    }

    public function isProgramManager()
    {
        return $this->role === 'Program Manager';
    }

    public function isDistributionManager()
    {
        return $this->role === 'Distribution Manager';
    }

    public function isManager()
    {
        return \App\Services\RoleHierarchyService::isManager($this->role);
    }

    public function isEmployee()
    {
        return \App\Services\RoleHierarchyService::isEmployee($this->role);
    }

    public function canViewEmployee($employeeId)
    {
        if (!$this->employee)
            return false;

        $subordinates = $this->employee->getSubordinatesByDepartment();
        return $subordinates->contains('id', $employeeId);
    }

    public function canApproveLeave($employeeId = null)
    {
        if ($employeeId && $this->employee) {
            return $this->employee->canApproveLeaveFor($employeeId);
        }
        return in_array($this->role, ['Manager', 'HR']);
    }

    public function canViewAllLeaveRequests()
    {
        return $this->role === 'HR';
    }

    /**
     * Check if user is an active member of ANY production team
     */
    public function hasAnyMusicTeamAssignment(): bool
    {
        return $this->productionTeamMembers()
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })->exists();
    }

    /**
     * Check if user is an active member of a SPECIFIC team type
     */
    public function hasMusicTeamAssignment(string $teamType): bool
    {
        return $this->productionTeamMembers()
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) use ($teamType) {
                $q->where('team_type', $teamType)
                    ->where('status', '!=', 'cancelled');
            })->exists();
    }

    /**
     * Get the name of the program the user is currently assigned to
     */
    public function getAssignedProgramName(): ?string
    {
        $activeMember = $this->productionTeamMembers()
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })
            ->with(['assignment.episode.program'])
            ->latest('joined_at')
            ->first();

        return $activeMember?->assignment?->episode?->program?->name;
    }

    /**
     * Check if user is assigned as a production crew for Program Regular episodes
     */
    public function hasProductionAssignment(): bool
    {
        return \App\Models\PrEpisodeCrew::where('user_id', $this->id)
            ->whereIn('role', ['produksi', 'setting_syuting', 'production', 'setting', 'syuting'])
            ->exists();
    }
    /**
     * Get all unique music workflow roles assigned to this user across all teams.
     * Returns an array of frontend-compatible role slugs.
     */
    public function getAssignedMusicWorkflowRoles(): array
    {
        // 1. Dapatkan role utama
        $primaryRole = $this->role;
        $primarySlug = $this->mapBackendRoleToFrontendSlug($primaryRole);

        // 2. Dapatkan role dari penugasan tim produksi
        $teamRoles = $this->productionTeamMembers()
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })
            ->pluck('role')
            ->unique();

        $assignedSlugs = [$primarySlug];
        foreach ($teamRoles as $role) {
            $slug = $this->mapBackendRoleToFrontendSlug($role);
            if ($slug && !in_array($slug, $assignedSlugs)) {
                $assignedSlugs[] = $slug;
            }
        }

        return array_values(array_filter($assignedSlugs));
    }

    /**
     * Check if user is assigned a specific role (slug) for a specific episode
     */
    public function hasRoleInEpisodeTeam(int $episodeId, string $roleSlug): bool
    {
        // Get all roles for this episode
        $teamRoles = $this->productionTeamMembers()
            ->where('is_active', true)
            ->whereHas('assignment', function ($q) use ($episodeId) {
                $q->where('episode_id', $episodeId)->where('status', '!=', 'cancelled');
            })
            ->pluck('role');

        foreach ($teamRoles as $role) {
            if ($this->mapBackendRoleToFrontendSlug($role) === $roleSlug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper to map various backend role strings to consistent frontend slugs
     */
    private function mapBackendRoleToFrontendSlug(string $role): string
    {
        $role = strtolower(trim($role));
        
        $mapping = [
            'program manager' => 'program_manager',
            'program_manager' => 'program_manager',
            'producer' => 'producer',
            'music arranger' => 'music_arranger',
            'music_arranger' => 'music_arranger',
            'musik_arr' => 'music_arranger',
            'musik_arr_song' => 'music_arranger',
            'sound engineer' => 'sound_engineer',
            'sound_engineer' => 'sound_engineer',
            'sound_eng' => 'sound_engineer',
            'creative' => 'creative',
            'kreatif' => 'creative',
            'producer_creative' => 'creative',
            'production' => 'production',
            'produksi' => 'production',
            'tim_setting_coord' => 'production',
            'tim_syuting_coord' => 'production',
            'editor' => 'editor',
            'art_set_design' => 'art_set_properti',
            'art_set_properti' => 'art_set_properti',
            'art set properti' => 'art_set_properti',
            'graph design' => 'design_grafis',
            'design_grafis' => 'design_grafis',
            'graphic design' => 'design_grafis',
            'promotion' => 'promosi',
            'promosi' => 'promosi',
            'broadcasting' => 'broadcasting',
            'quality control' => 'quality_control',
            'quality_control' => 'quality_control',
            'qc' => 'quality_control',
            'social_media' => 'social_media',
            'social media' => 'social_media',
            'editor_promotion' => 'editor_promosi',
            'editor_promosi' => 'editor_promosi',
            'distribution manager' => 'distribution_manager',
            'distribution_manager_qc' => 'distribution_manager',
            'broadcast_dist' => 'distribution_manager',
            'general affairs' => 'general_affairs',
            'general_affairs' => 'general_affairs',
        ];

        return $mapping[$role] ?? str_replace(' ', '_', $role);
    }
}