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

        $role = Role::normalize((string) $user->role);
        
        return $role === Role::PRODUCER || $role === Role::PROGRAM_MANAGER;
    }

    /**
     * Check if user is Distribution Manager or has higher privileges (Program Manager)
     */
    public static function hasDistributionManagerAccess(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        $role = Role::normalize((string) $user->role);
        
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

        $userRole = \App\Constants\Role::normalize((string) $user->role);
        $primaryRole = \App\Constants\Role::normalize($primaryRole);

        // 1. Explicitly assigned to this specific work (handles reassignment)
        if ($work && $work->$userField === $user->id) {
            return true;
        }

        // 2. User has the primary role for this task type
        if ($userRole === $primaryRole) {
            return true;
        }

        // 3. User is Program Manager (always allowed)
        if ($userRole === \App\Constants\Role::PROGRAM_MANAGER) {
            return true;
        }

        // 4. User is Producer (allowed based on specific access list)
        if ($userRole === \App\Constants\Role::PRODUCER && self::canProducerAccessRole($primaryRole)) {
            return true;
        }

        // 5. User is Distribution Manager (allowed based on specific access list)
        if ($userRole === \App\Constants\Role::DISTRIBUTION_MANAGER && self::canDistributionManagerAccessRole($primaryRole)) {
            return true;
        }

        return false;
    }
}
