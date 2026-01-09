<?php

namespace App\Services;

use App\Models\CustomRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseEnumService
{
    /**
     * Standard roles yang selalu ada
     * Includes HR roles, Production roles, Distribution roles, dan Music Program roles
     */
    private static $standardRoles = [
        // HR & Management
        'HR', 'Program Manager', 'Distribution Manager', 'GA',
        'Finance', 'General Affairs', 'Office Assistant',
        'VP President', 'President Director',
        // Production Roles
        'Producer', 'Creative', 'Production', 'Editor',
        // Distribution & Marketing Roles
        'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
        // Music Program Roles
        'Music Arranger', 
        'Sound Engineer', 'Sound Engineer Recording', 'Sound Engineer Editing',
        'Quality Control', 
        'Art & Set Properti',
        'Editor Promotion',
        'Broadcasting',
        // Default
        'Employee'
    ];

    /**
     * Update enum values di users dan employees table dengan custom roles
     */
    public static function updateRoleEnums()
    {
        try {
            $customRoles = [];
            
            // Cek apakah tabel custom_roles ada, jika tidak ada gunakan standard roles saja
            try {
                if (DB::getSchemaBuilder()->hasTable('custom_roles')) {
            $customRoles = CustomRole::where('is_active', true)
                ->pluck('role_name')
                ->toArray();
                }
            } catch (\Exception $e) {
                // Tabel custom_roles tidak ada, gunakan standard roles saja
                Log::info('Custom roles table not found, using standard roles only');
            }

            // Gabungkan standard roles dengan custom roles
            $allRoles = array_merge(self::$standardRoles, $customRoles);
            
            // Remove duplicates dan sort
            $allRoles = array_unique($allRoles);
            sort($allRoles);

            // SEBELUM update enum, update dulu data yang tidak valid menjadi 'Employee'
            // Update users table - ubah role yang tidak valid menjadi 'Employee'
            DB::table('users')
                ->whereNotIn('role', $allRoles)
                ->update(['role' => 'Employee']);
            
            // Update employees table - ubah jabatan_saat_ini yang tidak valid menjadi 'Employee'
            DB::table('employees')
                ->whereNotIn('jabatan_saat_ini', $allRoles)
                ->update(['jabatan_saat_ini' => 'Employee']);

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
     * Jika tabel custom_roles tidak ada, hanya return standard roles
     */
    public static function getAllAvailableRoles()
    {
        $customRoles = [];
        
        // Cek apakah tabel custom_roles ada
        try {
            if (DB::getSchemaBuilder()->hasTable('custom_roles')) {
        $customRoles = CustomRole::where('is_active', true)
            ->pluck('role_name')
            ->toArray();
            }
        } catch (\Exception $e) {
            // Tabel tidak ada, gunakan standard roles saja
            // Tidak perlu log error karena ini adalah kondisi normal jika custom_roles tidak digunakan
        }

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