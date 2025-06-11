<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'annual_leave_quota',
        'annual_leave_used',
        'sick_leave_quota',
        'sick_leave_used',
        'emergency_leave_quota',
        'emergency_leave_used',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Getter untuk sisa cuti tahunan
    public function getRemainingAnnualLeaveAttribute()
    {
        return $this->annual_leave_quota - $this->annual_leave_used;
    }

    // Getter untuk sisa cuti sakit
    public function getRemainingSickLeaveAttribute()
    {
        return $this->sick_leave_quota - $this->sick_leave_used;
    }

    // Getter untuk sisa cuti darurat
    public function getRemainingEmergencyLeaveAttribute()
    {
        return $this->emergency_leave_quota - $this->emergency_leave_used;
    }
}