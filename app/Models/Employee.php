<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_lengkap',
        'nik',
        'nip',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'status_pernikahan',
        'jabatan_saat_ini',
        'department',
        'manager_id',
        'tanggal_mulai_kerja',
        'tingkat_pendidikan',
        'gaji_pokok',
        'tunjangan',
        'bonus',
        'nomor_bpjs_kesehatan',
        'nomor_bpjs_ketenagakerjaan',
        'npwp',
        'nomor_kontrak',
        'tanggal_kontrak_berakhir',
    ];

    // Relationships
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function employmentHistories()
    {
        return $this->hasMany(EmploymentHistory::class);
    }

    public function promotionHistories()
    {
        return $this->hasMany(PromotionHistory::class);
    }

    public function trainings()
    {
        return $this->hasMany(Training::class);
    }

    public function benefits()
    {
        return $this->hasMany(Benefit::class);
    }

    public function leaveQuotas()
    {
        return $this->hasMany(LeaveQuota::class, 'employee_id');
    }
    
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }
    
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    
    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    // Manager hierarchy relationships
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    // Method untuk mendapatkan semua bawahan berdasarkan role
    public function getSubordinatesByRole()
    {
        $userRole = $this->user->role ?? null;
        
        if ($userRole === 'HR') {
            return Employee::whereIn('department', ['Finance', 'General Affairs', 'Office Assistant'])
                          ->where('id', '!=', $this->id)
                          ->get();
        } elseif ($userRole === 'Program Manager') {
            return Employee::whereIn('department', ['Producer', 'Creative', 'Production', 'Editor'])
                          ->where('id', '!=', $this->id)
                          ->get();
        } elseif ($userRole === 'Distribution Manager') {
            return Employee::whereIn('department', ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'])
                          ->where('id', '!=', $this->id)
                          ->get();
        }
        
        return collect();
    }

    // Method untuk cek apakah user bisa approve leave request
    public function canApproveLeaveFor($employeeId)
    {
        $userRole = $this->user->role ?? null;
        $targetEmployee = Employee::find($employeeId);
        
        if (!$targetEmployee) return false;
        
        if ($userRole === 'HR') {
            return in_array($targetEmployee->department, ['Finance', 'General Affairs', 'Office Assistant']);
        } elseif ($userRole === 'Program Manager') {
            return in_array($targetEmployee->department, ['Producer', 'Creative', 'Production', 'Editor']);
        } elseif ($userRole === 'Distribution Manager') {
            return in_array($targetEmployee->department, ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care']);
        }
        
        return false;
    }

    // Get current year leave quota
    public function getCurrentLeaveQuota()
    {
        return $this->leaveQuotas()->where('year', date('Y'))->first();
    }

    // Check if employee can take leave
    public function canTakeLeave($leaveType, $days)
    {
        $quota = $this->getCurrentLeaveQuota();
        if (!$quota) return false;
        
        switch ($leaveType) {
            case 'annual':
                return ($quota->annual_leave_used + $days) <= $quota->annual_leave_quota;
            case 'sick':
                return ($quota->sick_leave_used + $days) <= $quota->sick_leave_quota;
            case 'emergency':
                return ($quota->emergency_leave_used + $days) <= $quota->emergency_leave_quota;
            default:
                return false;
        }
    }

    // Update leave quota when leave is approved
    public function updateLeaveQuota($leaveType, $days)
    {
        $quota = $this->getCurrentLeaveQuota();
        if ($quota) {
            switch ($leaveType) {
                case 'annual':
                    $quota->annual_leave_used += $days;
                    break;
                case 'sick':
                    $quota->sick_leave_used += $days;
                    break;
                case 'emergency':
                    $quota->emergency_leave_used += $days;
                    break;
            }
            $quota->save();
        }
    }
}