<?php
// C:\laragon\www\backend_hci_hrd\app\Services\RoleHierarchyService.php

namespace App\Services;

class RoleHierarchyService
{
    protected static $hierarchy = [
        'HR Manager' => ['Finance', 'General Affairs', 'Office Assistant'],
        'Program Manager' => ['Producer', 'Creative', 'Production', 'Editor'],
        'Distribution Manager' => ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'],
    ];

    // BARU: Fungsi untuk mengecek apakah role adalah HR Manager
    public static function isHrManager($role): bool
    {
        return $role === 'HR Manager';
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

        $subordinates = self::getSubordinateRoles($approverRole);
        return in_array($employeeRole, $subordinates);
    }
}