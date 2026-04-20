<?php

namespace App\Helpers;

use App\Constants\Role;
use App\Models\User;

class MusicProgramAuthorization
{
    /**
     * Check if user is Producer or has higher privileges (Program Manager)
     */
    public static function hasProducerAccess(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) data_get($user, 'role'));
        
        return $role === Role::PRODUCER || $role === Role::PROGRAM_MANAGER;
    }

    /**
     * Check if user is Sound Engineer
     */
    public static function isSoundEngineer(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) data_get($user, 'role'));
        
        return $role === Role::SOUND_ENGINEER;
    }

    /**
     * Check if user is Music Arranger
     */
    public static function isArranger(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) data_get($user, 'role'));
        
        return $role === Role::MUSIC_ARRANGER;
    }

    /**
     * Check if user is Distribution Manager or has higher privileges (Program Manager)
     */
    public static function hasDistributionManagerAccess(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) data_get($user, 'role'));
        
        return $role === Role::DISTRIBUTION_MANAGER || $role === Role::PROGRAM_MANAGER;
    }

    /**
     * Check if Producer can access a specific role's data
     */
    public static function canProducerAccessRole(string $targetRole): bool
    {
        $targetRole = Role::normalize($targetRole);
        
        $allowedRoles = [
            Role::MUSIC_ARRANGER,
            Role::SOUND_ENGINEER,
            Role::CREATIVE,
            Role::GENERAL_AFFAIRS,
            Role::PRODUCTION,
            Role::EDITOR,
            Role::ART_SET_PROPERTI,
        ];

        return in_array($targetRole, $allowedRoles);
    }

    /**
     * Check if Distribution Manager can access a specific role's data
     */
    public static function canDistributionManagerAccessRole(string $targetRole): bool
    {
        $targetRole = Role::normalize($targetRole);
        
        $allowedRoles = [
            Role::PROMOTION,
            Role::GRAPHIC_DESIGN,
            Role::EDITOR_PROMOTION,
            Role::QUALITY_CONTROL,
            Role::EDITOR,
            Role::BROADCASTING,
            Role::SOCIAL_MEDIA,
        ];

        return in_array($targetRole, $allowedRoles);
    }
    /**
     * Check if a user is allowed to perform a specific task/work.
     * Access is granted if:
     * 1. User is the creator/assignee of the task (reassigned case)
     * 2. User has the primary role for the task
     * 3. User is a Program Manager or Producer (admin oversight)
     * 
     * @param User $user
     * @param mixed $work The work model instance
     * @param string $primaryRole The primary role allowed for this task (e.g. 'Editor')
     * @param string $userField The field in the model that stores the assigned user (e.g. 'created_by' or 'assigned_to')
     * @return bool
     */
    public static function canUserPerformTask(?\Illuminate\Contracts\Auth\Authenticatable $user, $work, string $primaryRole, string $userField = 'created_by'): bool
    {
        if (!$user) return false;

        $userRole = Role::normalize((string) data_get($user, 'role'));
        $primaryRole = Role::normalize($primaryRole);

        // 1. Explicitly assigned to this specific work (handles reassignment)
        if ($work && data_get($work, $userField) === data_get($user, 'id')) {
            return true;
        }

        // 2. User has the primary role for this task type
        if ($userRole === $primaryRole) {
            return true;
        }

        // 3. User is Program Manager (always allowed)
        if ($userRole === Role::PROGRAM_MANAGER) {
            return true;
        }

        // 4. User is Producer (allowed based on specific access list)
        if ($userRole === Role::PRODUCER && self::canProducerAccessRole($primaryRole)) {
            return true;
        }

        // 5. User is Distribution Manager (allowed based on specific access list)
        if ($userRole === Role::DISTRIBUTION_MANAGER && self::canDistributionManagerAccessRole($primaryRole)) {
            return true;
        }

        // 6. Episode-specific team assignment check (for Production / Vocal tasks)
        // If the user doesn't have the global role, they might still be assigned to this specific episode's team.
        if ($work && isset($work->episode_id)) {
            $epId = (int) $work->episode_id;
            
            // Allow if assigned to ANY tech team for this episode (setting, shooting, recording)
            $isAssigned = \App\Models\ProductionTeamMember::where('user_id', $user->id)
                ->whereHas('assignment', function ($q) use ($epId) {
                    $q->where('episode_id', $epId)->where('status', '!=', 'cancelled');
                })->exists();
                
            if ($isAssigned) return true;
        }

        // 7. Check for Task Reassignments (Backup/Substitution System)
        if ($work && isset($work->episode_id)) {
            $epId = (int) $work->episode_id;
            $hasReassignment = \App\Models\TaskReassignment::where('episode_id', $epId)
                ->where('new_user_id', $user->id)
                ->where(function($q) use ($primaryRole) {
                    $q->where('role_key', $primaryRole)
                      ->orWhere('task_type', $primaryRole);
                })->exists();
            
            if ($hasReassignment) return true;
        }

        return false;
    }

    /**
     * Check if a user has access to Art & Set Properti resources
     * (Inventory, Requests, Templates)
     */
    public static function canAccessArtSetProperti(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) data_get($user, 'role'));

        // 1. Roles with direct access
        $directAccessRoles = [
            Role::ART_SET_PROPERTI,
            Role::PRODUCTION,
            Role::SOUND_ENGINEER, // Explicitly include Sound Engineer
            Role::PRODUCER,
            Role::PROGRAM_MANAGER,
            Role::DISTRIBUTION_MANAGER,
        ];

        if (in_array($role, $directAccessRoles)) {
            return true;
        }

        // 2. Promotion & Social Media (view context)
        if (in_array($role, [Role::PROMOTION, Role::SOCIAL_MEDIA])) {
            return true;
        }

        // 3. User with Music Team assignment
        if (method_exists($user, 'hasAnyMusicTeamAssignment') && $user->hasAnyMusicTeamAssignment()) {
            return true;
        }

        // 4. Special setting assignment
        if (method_exists($user, 'hasMusicTeamAssignment') && $user->hasMusicTeamAssignment('setting')) {
            return true;
        }

        return false;
    }
}
