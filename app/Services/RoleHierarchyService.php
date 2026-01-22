<?php
// C:\laragon\www\backend_hci_hrd\app\Services\RoleHierarchyService.php

namespace App\Services;
use App\Models\CustomRole;

class RoleHierarchyService
{
    // Standard hierarchy - Updated untuk support Producer dan Distribution Manager sebagai manager
    protected static $standardHierarchy = [
        'HR' => ['Finance', 'General Affairs', 'Office Assistant'],
        'Program Manager' => [
            'Producer',
            'Distribution Manager',
            'Creative',
            'Production',
            'Art & Set Properti',
            'Editor',
            'Quality Control',
            'Promotion',
            'Graphic Design',
            'Editor Promotion',
            'Broadcasting'
        ],
        'Producer' => [
            'Creative',
            'Production',
            'Art & Set Properti',
            'Editor',
            'Quality Control'
        ],
        'Distribution Manager' => [
            'Promotion',
            'Graphic Design',
            'Editor Promotion',
            'Quality Control',
            'Broadcasting'
        ],
    ];

    // Department mapping untuk standard roles
    protected static $standardDepartmentMapping = [
        'HR' => 'hr',
        'Finance' => 'hr',
        'General Affairs' => 'hr',
        'Office Assistant' => 'hr',
        'Program Manager' => 'production',
        'Producer' => 'production',
        'Creative' => 'production',
        'Production' => 'production',
        'Art & Set Properti' => 'production',
        'Editor' => 'production',
        'Quality Control' => 'production', // QC primarily under production, but can work with distribution
        'Distribution Manager' => 'distribution',
        'Social Media' => 'distribution',
        'Promotion' => 'distribution',
        'Graphic Design' => 'distribution',
        'Editor Promotion' => 'distribution',
        'Broadcasting' => 'distribution',
        'Hopeline Care' => 'distribution',
        'VP President' => 'executive',
        'President Director' => 'executive'
    ];

    // Role dengan akses read-only seperti HR
    protected static $readOnlyRoles = [
        'VP President',
        'President Director'
    ];

    // BARU: Mendapatkan hierarchy yang lengkap (standard + custom)
    public static function getFullHierarchy(): array
    {
        $hierarchy = self::$standardHierarchy;

        // Tambahkan custom roles ke hierarchy
        $customRoles = CustomRole::with('supervisor')
            ->where('is_active', true)
            ->where('access_level', 'manager')
            ->get();

        foreach ($customRoles as $customRole) {
            $subordinates = CustomRole::where('supervisor_id', $customRole->id)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            if (!empty($subordinates)) {
                $hierarchy[$customRole->role_name] = $subordinates;
            }
        }

        return $hierarchy;
    }

    // BARU: Mendapatkan supervisor untuk role tertentu
    public static function getSupervisorForRole($roleName): ?string
    {
        // Cek di standard hierarchy
        foreach (self::$standardHierarchy as $manager => $subordinates) {
            if (in_array($roleName, $subordinates)) {
                return $manager;
            }
        }

        // Cek di custom roles
        $customRole = CustomRole::where('role_name', $roleName)
            ->where('is_active', true)
            ->with('supervisor')
            ->first();

        if ($customRole && $customRole->supervisor) {
            return $customRole->supervisor->role_name;
        }

        return null;
    }

    // BARU: Mendapatkan department untuk role
    public static function getDepartmentForRole($roleName): ?string
    {
        // Cek di standard mapping
        if (isset(self::$standardDepartmentMapping[$roleName])) {
            return self::$standardDepartmentMapping[$roleName];
        }

        // Cek di custom roles
        $customRole = CustomRole::where('role_name', $roleName)
            ->where('is_active', true)
            ->first();

        return $customRole ? $customRole->department : null;
    }

    // BARU: Mendapatkan semua subordinates untuk manager
    public static function getAllSubordinates($managerRole): array
    {
        $subordinates = [];

        // Ambil dari standard hierarchy
        if (isset(self::$standardHierarchy[$managerRole])) {
            $subordinates = array_merge($subordinates, self::$standardHierarchy[$managerRole]);
        }

        // Ambil dari custom roles
        $customManager = CustomRole::where('role_name', $managerRole)
            ->where('is_active', true)
            ->first();

        if ($customManager) {
            $customSubordinates = CustomRole::where('supervisor_id', $customManager->id)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            $subordinates = array_merge($subordinates, $customSubordinates);
        }

        return array_unique($subordinates);
    }

    // BARU: Mendapatkan semua roles berdasarkan department
    public static function getRolesByDepartment($department): array
    {
        $roles = [];

        // Ambil dari standard roles
        foreach (self::$standardDepartmentMapping as $role => $dept) {
            if ($dept === $department) {
                $roles[] = $role;
            }
        }

        // Ambil dari custom roles
        $customRoles = CustomRole::where('department', $department)
            ->where('is_active', true)
            ->pluck('role_name')
            ->toArray();

        return array_merge($roles, $customRoles);
    }

    // BARU: Mendapatkan semua managers yang tersedia
    public static function getAvailableManagers(): array
    {
        $managers = array_keys(self::$standardHierarchy);

        // Tambahkan custom managers
        $customManagers = CustomRole::where('access_level', 'manager')
            ->where('is_active', true)
            ->pluck('role_name')
            ->toArray();

        return array_merge($managers, $customManagers);
    }

    // BARU: Validasi hierarchy (mencegah circular reference)
    public static function validateHierarchy($roleId, $supervisorId): bool
    {
        if (!$supervisorId)
            return true; // Boleh tidak punya supervisor

        // Cek apakah supervisor adalah role yang sama
        if ($roleId == $supervisorId)
            return false;

        // Cek apakah supervisor adalah subordinate dari role ini (circular reference)
        $supervisor = CustomRole::find($supervisorId);
        if (!$supervisor)
            return true; // Standard manager, tidak ada circular reference

        // Cek apakah supervisor memiliki role ini sebagai supervisor
        return $supervisor->supervisor_id != $roleId;
    }

    // Update method canApproveLeave untuk mendukung custom hierarchy
    public static function canApproveLeave($approverRole, $employeeRole): bool
    {
        // Cek apakah approver adalah manager
        if (!self::isManager($approverRole)) {
            return false;
        }

        // Cek di standard hierarchy
        if (isset(self::$standardHierarchy[$approverRole])) {
            if (in_array($employeeRole, self::$standardHierarchy[$approverRole])) {
                return true;
            }
        }

        // DIPERBARUI: Cek di custom hierarchy berdasarkan department
        $approverDepartment = self::getDepartmentForRole($approverRole);
        $employeeDepartment = self::getDepartmentForRole($employeeRole);

        // Jika department sama dan employee adalah custom role dengan access_level employee
        if ($approverDepartment && $employeeDepartment && $approverDepartment === $employeeDepartment) {
            $customEmployee = CustomRole::where('role_name', $employeeRole)
                ->where('access_level', 'employee')
                ->where('is_active', true)
                ->first();

            if ($customEmployee) {
                return true;
            }
        }

        // Cek di custom hierarchy berdasarkan supervisor_id
        $customManager = CustomRole::where('role_name', $approverRole)
            ->where('is_active', true)
            ->first();

        if ($customManager) {
            $subordinates = CustomRole::where('supervisor_id', $customManager->id)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            return in_array($employeeRole, $subordinates);
        }

        return false;
    }

    // Fungsi untuk mengecek apakah role adalah HR Manager
    public static function isHrManager($role): bool
    {
        return $role === 'HR';
    }

    // Fungsi untuk mengecek apakah role memiliki akses read-only seperti HR
    public static function isReadOnlyRole($role): bool
    {
        return in_array($role, self::$readOnlyRoles);
    }

    // Fungsi untuk mengecek apakah role adalah VP President atau President Director
    public static function isExecutiveRole($role): bool
    {
        return in_array($role, self::$readOnlyRoles);
    }

    // Fungsi untuk mengecek apakah role adalah manager non-HR
    public static function isOtherManager($role): bool
    {
        return in_array($role, ['Program Manager', 'Distribution Manager']);
    }

    // Fungsi untuk mengecek semua jenis manager
    public static function isManager($role): bool
    {
        $fullHierarchy = self::getFullHierarchy();
        return isset($fullHierarchy[$role]);
    }

    // Fungsi untuk mengecek apakah role adalah employee (non-manager)
    public static function isEmployee($role): bool
    {
        $fullHierarchy = self::getFullHierarchy();
        $employeeRoles = array_merge(...array_values($fullHierarchy));
        return in_array($role, $employeeRoles);
    }

    public static function getSubordinateRoles($managerRole): array
    {
        $subordinates = [];

        // Ambil dari standard hierarchy
        if (isset(self::$standardHierarchy[$managerRole])) {
            $subordinates = array_merge($subordinates, self::$standardHierarchy[$managerRole]);
        }

        // DIPERBARUI: Tambahkan custom roles berdasarkan department mapping
        $managerDepartment = self::getDepartmentForRole($managerRole);

        if ($managerDepartment) {
            // Ambil semua custom roles dengan department yang sama dan access_level employee
            $customSubordinates = CustomRole::where('department', $managerDepartment)
                ->where('access_level', 'employee')
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            $subordinates = array_merge($subordinates, $customSubordinates);
        }

        // Cek juga custom roles yang memiliki supervisor_id yang mengarah ke manager ini
        $customManager = CustomRole::where('role_name', $managerRole)
            ->where('is_active', true)
            ->first();

        if ($customManager) {
            $directSubordinates = CustomRole::where('supervisor_id', $customManager->id)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            $subordinates = array_merge($subordinates, $directSubordinates);
        }

        return array_unique($subordinates);
    }

    // Mendapatkan semua role yang tersedia (termasuk custom roles)
    public static function getAllAvailableRoles(): array
    {
        return DatabaseEnumService::getAllAvailableRoles();
    }

    // Mengecek apakah role adalah custom role
    public static function isCustomRole($role): bool
    {
        return CustomRole::where('role_name', $role)->where('is_active', true)->exists();
    }

    // Mendapatkan access level dari custom role
    public static function getCustomRoleAccessLevel($role): ?string
    {
        $customRole = CustomRole::where('role_name', $role)->where('is_active', true)->first();
        return $customRole ? $customRole->access_level : null;
    }

    // Mendapatkan semua role read-only
    public static function getReadOnlyRoles(): array
    {
        return self::$readOnlyRoles;
    }

    // Mendapatkan manager roles
    public static function getManagerRoles(): array
    {
        return self::getAvailableManagers();
    }

    public static function getEmployeeRoles(): array
    {
        $fullHierarchy = self::getFullHierarchy();
        $standardEmployeeRoles = array_merge(...array_values($fullHierarchy));

        // Tambahkan custom roles yang aktif dengan access level employee
        $customEmployeeRoles = CustomRole::active()
            ->byAccessLevel('employee')
            ->pluck('role_name')
            ->toArray();

        return array_merge($standardEmployeeRoles, $customEmployeeRoles);
    }

    /**
     * Get list of roles that a user can access/filter by
     * Used for role filtering dropdown in Program Regular
     *
     * @param string $userRole
     * @return array Array of roles the user can view data for
     */
    public static function getRolesAccessibleByUser(string $userRole): array
    {
        // Normalize role name for consistency
        $normalizedRole = \App\Constants\Role::normalize($userRole);

        // Check if user role is a manager with subordinates
        if (isset(self::$standardHierarchy[$normalizedRole])) {
            return self::$standardHierarchy[$normalizedRole];
        }

        // Check custom roles
        $customManager = CustomRole::where('role_name', $normalizedRole)
            ->where('is_active', true)
            ->first();

        if ($customManager) {
            $subordinates = CustomRole::where('supervisor_id', $customManager->id)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            return $subordinates;
        }

        // If not a manager, return empty array
        return [];
    }

    /**
     * Check if a user with given role can access data for target role
     *
     * @param string $userRole The role of the current user
     * @param string $targetRole The role to filter/view data for
     * @return bool True if user can access target role data
     */
    public static function canAccessRoleData(string $userRole, string $targetRole): bool
    {
        // Normalize role names for case-insensitive comparison
        $normalizedUserRole = \App\Constants\Role::normalize($userRole);
        $normalizedTargetRole = \App\Constants\Role::normalize($targetRole);

        // User can always view their own role's data
        if ($normalizedUserRole === $normalizedTargetRole) {
            return true;
        }

        // Check if target role is in user's accessible roles
        $accessibleRoles = self::getRolesAccessibleByUser($normalizedUserRole);

        // Also normalize accessible roles for comparison
        $normalizedAccessibleRoles = array_map(function ($role) {
            return \App\Constants\Role::normalize($role);
        }, $accessibleRoles);

        return in_array($normalizedTargetRole, $normalizedAccessibleRoles);
    }
}