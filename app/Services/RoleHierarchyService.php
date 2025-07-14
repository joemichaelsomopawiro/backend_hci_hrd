<?php
// C:\laragon\www\backend_hci_hrd\app\Services\RoleHierarchyService.php

namespace App\Services;
use App\Models\CustomRole;

class RoleHierarchyService
{
    protected static $hierarchy = [
        'HR' => ['Finance', 'General Affairs', 'Office Assistant'], // UBAH dari 'HR Manager' ke 'HR'
        'Program Manager' => ['Producer', 'Creative', 'Production', 'Editor'],
        'Distribution Manager' => ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'],
    ];
    
    // Role dengan akses read-only seperti HR
    protected static $readOnlyRoles = [
        'VP President',
        'President Director'
    ];

    // BARU: Fungsi untuk mengecek apakah role adalah HR Manager
    public static function isHrManager($role): bool
    {
        return $role === 'HR'; // UBAH dari 'HR Manager' ke 'HR'
    }
    
    // BARU: Fungsi untuk mengecek apakah role memiliki akses read-only seperti HR
    public static function isReadOnlyRole($role): bool
    {
        return in_array($role, self::$readOnlyRoles) || self::isHrManager($role);
    }
    
    // BARU: Fungsi untuk mengecek apakah role adalah VP President atau President Director
    public static function isExecutiveRole($role): bool
    {
        return in_array($role, self::$readOnlyRoles);
    }

    // BARU: Fungsi untuk mengecek apakah role adalah manager non-HR
    public static function isOtherManager($role): bool
    {
        return in_array($role, ['Program Manager', 'Distribution Manager']);
    }

    // TETAP SAMA: Fungsi untuk mengecek semua jenis manager
    public static function isManager($role): bool
    {
        return isset(self::$hierarchy[$role]);
    }

    // BARU: Fungsi untuk mengecek apakah role adalah employee (non-manager)
    public static function isEmployee($role): bool
    {
        // Mengumpulkan semua role bawahan menjadi satu array
        $employeeRoles = array_merge(...array_values(self::$hierarchy));
        return in_array($role, $employeeRoles);
    }

    public static function getSubordinateRoles($managerRole): array
    {
        return self::$hierarchy[$managerRole] ?? [];
    }
    
    public static function canApproveLeave($approverRole, $employeeRole): bool
    {
        if (!self::isManager($approverRole)) {
            return false;
        }

        // HR hanya bisa approve cuti dari bawahannya langsung
        // Tidak bisa approve cuti dari Program Manager atau Distribution Manager
        if ($approverRole === 'HR') {
            $hrSubordinates = self::getSubordinateRoles('HR');
            return in_array($employeeRole, $hrSubordinates);
        }

        // Manager lain (Program Manager, Distribution Manager) hanya bisa approve bawahannya
        $subordinates = self::getSubordinateRoles($approverRole);
        return in_array($employeeRole, $subordinates);
    }
    
    // TAMBAHKAN METHOD INI:
    public static function getManagerRoles(): array
    {
        return array_keys(self::$hierarchy);
    }
    
    public static function getEmployeeRoles(): array
    {
        $standardEmployeeRoles = array_merge(...array_values(self::$hierarchy));
        
        // Tambahkan custom roles yang aktif dengan access level employee
        $customEmployeeRoles = CustomRole::active()
            ->byAccessLevel('employee')
            ->pluck('role_name')
            ->toArray();
            
        return array_merge($standardEmployeeRoles, $customEmployeeRoles);
    }
    
    // BARU: Mendapatkan semua role read-only
    public static function getReadOnlyRoles(): array
    {
        return self::$readOnlyRoles;
    }
    
    // BARU: Mendapatkan semua role yang tersedia (termasuk custom roles)
    public static function getAllAvailableRoles(): array
    {
        // Gunakan DatabaseEnumService untuk konsistensi
        return DatabaseEnumService::getAllAvailableRoles();
    }
    
    // BARU: Mengecek apakah role adalah custom role
    public static function isCustomRole($role): bool
    {
        return CustomRole::where('role_name', $role)->where('is_active', true)->exists();
    }
    
    // BARU: Mendapatkan access level dari custom role
    public static function getCustomRoleAccessLevel($role): ?string
    {
        $customRole = CustomRole::where('role_name', $role)->where('is_active', true)->first();
        return $customRole ? $customRole->access_level : null;
    }
}