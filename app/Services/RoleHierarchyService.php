<?php

namespace App\Services;

class RoleHierarchyService
{
    public static function getManagerRoles()
    {
        return ['HR', 'Program Manager', 'Distribution Manager'];
    }
    
    public static function getEmployeeRoles()
    {
        return [
            'Finance', 
            'General Affairs', 
            'Office Assistant', 
            'Producer', 
            'Creative', 
            'Production', 
            'Editor', 
            'Social Media', 
            'Promotion', 
            'Graphic Design', 
            'Hopeline Care'
        ];
    }
    
    public static function getSubordinateRoles($managerRole)
    {
        $hierarchy = [
            'HR' => ['Finance', 'General Affairs', 'Office Assistant'],
            'Program Manager' => ['Producer', 'Creative', 'Production', 'Editor'],
            'Distribution Manager' => ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care']
        ];
        
        return $hierarchy[$managerRole] ?? [];
    }
    
    public static function getManagerForRole($role)
    {
        $hierarchy = [
            'Finance' => 'HR',
            'General Affairs' => 'HR',
            'Office Assistant' => 'HR',
            'Producer' => 'Program Manager',
            'Creative' => 'Program Manager',
            'Production' => 'Program Manager',
            'Editor' => 'Program Manager',
            'Social Media' => 'Distribution Manager',
            'Promotion' => 'Distribution Manager',
            'Graphic Design' => 'Distribution Manager',
            'Hopeline Care' => 'Distribution Manager'
        ];
        
        return $hierarchy[$role] ?? null;
    }
    
    public static function isManager($role)
    {
        return in_array($role, self::getManagerRoles());
    }
    
    public static function isEmployee($role)
    {
        return in_array($role, self::getEmployeeRoles());
    }
    
    public static function canApproveLeave($userRole, $employeeRole)
    {
        // Cek apakah user adalah manager dari employee role tersebut
        $managerForEmployee = self::getManagerForRole($employeeRole);
        return $userRole === $managerForEmployee;
    }
}