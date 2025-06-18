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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isPhoneVerified()
    {
        return !is_null($this->phone_verified_at);
    }

    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            return asset('storage/' . $this->profile_picture);
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
        return $this->role === 'Manager' && 
               $this->employee && 
               in_array($this->employee->department, ['Producer', 'Creative', 'Production', 'Editor']);
    }

    public function isDistributionManager()
    {
        return $this->role === 'Manager' && 
               $this->employee && 
               in_array($this->employee->department, ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care']);
    }

    public function isManager()
    {
        return $this->role === 'Manager';
    }

    public function isEmployee()
    {
        return $this->role === 'Employee';
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