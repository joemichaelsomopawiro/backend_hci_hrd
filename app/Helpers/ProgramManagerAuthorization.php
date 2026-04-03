<?php

namespace App\Helpers;

use App\Constants\Role;
use App\Models\User;

class ProgramManagerAuthorization
{
    /**
     * Detect Program Manager role with normalization.
     *
     * Supported role variations (examples):
     * - Program Manager
     * - program_manager
     * - managerprogram
     * - etc (handled by Role::normalize)
     */
    public static function isProgramManager(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;

        return Role::normalize((string) data_get($user, 'role')) === Role::PROGRAM_MANAGER;
    }
}

