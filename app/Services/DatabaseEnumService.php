<?php

namespace App\Services;

use App\Models\CustomRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseEnumService
{
    /**
     * Standard roles yang selalu ada
     */
    private static $standardRoles = [
        'HR', 'Program Manager', 'Distribution Manager', 'GA',
        'Finance', 'General Affairs', 'Office Assistant',
        'Producer', 'Creative', 'Production', 'Editor',
        'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
        'VP President', 'President Director',
        'Employee'
    ];

    /**
     * Update enum values di users dan employees table dengan custom roles
     */
    public static function updateRoleEnums()
    {
        try {
            // Ambil semua custom roles yang aktif
            $customRoles = CustomRole::where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            // Gabungkan standard roles dengan custom roles
            $allRoles = array_merge(self::$standardRoles, $customRoles);
            
            // Remove duplicates dan sort
            $allRoles = array_unique($allRoles);
            sort($allRoles);

            // Format untuk SQL enum
            $enumValues = "'" . implode("', '", $allRoles) . "'";

            // Update users table
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM({$enumValues}) DEFAULT 'Employee'");
            
            // Update employees table
            DB::statement("ALTER TABLE employees MODIFY COLUMN jabatan_saat_ini ENUM({$enumValues}) DEFAULT 'Employee'");

            Log::info('Database enum values updated successfully', [
                'total_roles' => count($allRoles),
                'custom_roles_count' => count($customRoles)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update database enum values: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all available roles (standard + custom)
     */
    public static function getAllAvailableRoles()
    {
        $customRoles = CustomRole::where('is_active', true)
            ->pluck('role_name')
            ->toArray();

        return array_merge(self::$standardRoles, $customRoles);
    }

    /**
     * Check if a role exists in enum
     */
    public static function roleExistsInEnum($roleName)
    {
        $availableRoles = self::getAllAvailableRoles();
        return in_array($roleName, $availableRoles);
    }
}