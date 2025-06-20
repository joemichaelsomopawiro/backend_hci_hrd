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
        'maternity_leave_quota',
        'maternity_leave_used',
        'paternity_leave_quota',
        'paternity_leave_used',
        'marriage_leave_quota',
        'marriage_leave_used',
        'bereavement_leave_quota',
        'bereavement_leave_used',
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

    // Getter untuk sisa cuti melahirkan
    public function getRemainingMaternityLeaveAttribute()
    {
        return $this->maternity_leave_quota - $this->maternity_leave_used;
    }

    // Getter untuk sisa cuti ayah
    public function getRemainingPaternityLeaveAttribute()
    {
        return $this->paternity_leave_quota - $this->paternity_leave_used;
    }

    // Getter untuk sisa cuti menikah
    public function getRemainingMarriageLeaveAttribute()
    {
        return $this->marriage_leave_quota - $this->marriage_leave_used;
    }

    // Getter untuk sisa cuti duka
    public function getRemainingBereavementLeaveAttribute()
    {
        return $this->bereavement_leave_quota - $this->bereavement_leave_used;
    }
}