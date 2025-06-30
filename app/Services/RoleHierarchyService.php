<?php
// C:\laragon\www\backend_hci_hrd\app\Services\RoleHierarchyService.php

namespace App\Services;

class RoleHierarchyService
{
    protected static $hierarchy = [
        'HR' => ['Finance', 'General Affairs', 'Office Assistant'], // UBAH dari 'HR Manager' ke 'HR'
        'Program Manager' => ['Producer', 'Creative', 'Production', 'Editor'],
        'Distribution Manager' => ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'],
    ];

    // BARU: Fungsi untuk mengecek apakah role adalah HR Manager
    public static function isHrManager($role): bool
    {
        return $role === 'HR'; // UBAH dari 'HR Manager' ke 'HR'
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
        return array_merge(...array_values(self::$hierarchy));
    }
}