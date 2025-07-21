<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'description',
        'access_level',
        'department',
        'supervisor_id',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationship dengan user yang membuat role
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship dengan supervisor (atasan)
    public function supervisor()
    {
        return $this->belongsTo(CustomRole::class, 'supervisor_id');
    }

    // Relationship dengan subordinates (bawahan)
    public function subordinates()
    {
        return $this->hasMany(CustomRole::class, 'supervisor_id');
    }

    // Scope untuk role yang aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope berdasarkan access level
    public function scopeByAccessLevel($query, $level)
    {
        return $query->where('access_level', $level);
    }

    // Scope berdasarkan department
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    // Method untuk mengecek apakah role memiliki akses tertentu
    public function hasEmployeeAccess()
    {
        return in_array($this->access_level, ['employee', 'manager', 'hr_readonly', 'hr_full']);
    }

    public function hasManagerAccess()
    {
        return in_array($this->access_level, ['manager', 'hr_readonly', 'hr_full']);
    }

    public function hasHrReadonlyAccess()
    {
        return in_array($this->access_level, ['hr_readonly', 'hr_full']);
    }

    public function hasHrFullAccess()
    {
        return $this->access_level === 'hr_full';
    }

    // Method untuk mendapatkan department options
    public static function getDepartmentOptions()
    {
        return [
            'hr' => 'HR & Finance',
            'production' => 'Production',
            'distribution' => 'Distribution & Marketing',
            'executive' => 'Executive'
        ];
    }

    // Method untuk mendapatkan access level options
    public static function getAccessLevelOptions()
    {
        return [
            'employee' => 'Employee (Karyawan)',
            'manager' => 'Manager (Manajer)',
            'hr_readonly' => 'HR Read-Only (HR Hanya Lihat)',
            'hr_full' => 'HR Full Access (HR Akses Penuh)',
            'director' => 'Director (Direktur)'
        ];
    }

    // Method untuk mendapatkan supervisor options
    public static function getSupervisorOptions()
    {
        $managers = self::where('access_level', 'manager')
            ->where('is_active', true)
            ->pluck('role_name', 'id')
            ->toArray();

        // Tambahkan standard managers
        $standardManagers = [
            'HR' => 'HR',
            'Program Manager' => 'Program Manager',
            'Distribution Manager' => 'Distribution Manager'
        ];

        return array_merge($standardManagers, $managers);
    }
}