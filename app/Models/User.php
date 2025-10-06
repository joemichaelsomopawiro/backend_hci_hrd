<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'phone_verified_at',
        'profile_picture',
        'employee_id',
        'role',
        'access_level',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'notification_preferences' => 'array',
    ];

    protected $appends = ['profile_picture_url']; // <-- Tambahkan baris ini

    public function isPhoneVerified()
    {
        return !is_null($this->phone_verified_at);
    }

    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            // Use localhost:8000 for development
            return 'http://localhost:8000/storage/' . $this->profile_picture;
        }
        return null;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getEmployeeDataAttribute()
    {
        return $this->employee;
    }

    // Helper methods untuk role
    public function isHR()
    {
        return $this->role === 'HR';
    }

    public function isProgramManager()
    {
        return $this->role === 'Program Manager';
    }

    public function isDistributionManager()
    {
        return $this->role === 'Distribution Manager';
    }

    public function isManager()
    {
        return \App\Services\RoleHierarchyService::isManager($this->role);
    }

    public function isEmployee()
    {
        return \App\Services\RoleHierarchyService::isEmployee($this->role);
    }

    public function canViewEmployee($employeeId)
    {
        if (!$this->employee) return false;
        
        $subordinates = $this->employee->getSubordinatesByDepartment();
        return $subordinates->contains('id', $employeeId);
    }

    public function canApproveLeave($employeeId = null)
    {
        if ($employeeId && $this->employee) {
            return $this->employee->canApproveLeaveFor($employeeId);
        }
        return in_array($this->role, ['Manager', 'HR']);
    }

    public function canViewAllLeaveRequests()
    {
        return $this->role === 'HR';
    }
}