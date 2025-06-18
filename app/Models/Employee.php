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
        return $this->hasMany(LeaveQuota::class);
    }
    
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
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
        return $this->hasOne(User::class);
    }

    // Relasi untuk manager
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    // Relasi untuk subordinates (bawahan)
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    // Method untuk mendapatkan semua bawahan berdasarkan department
    public function getSubordinatesByDepartment()
    {
        $userRole = $this->user->role ?? null;
        
        if ($userRole === 'HR') {
            // HR bisa lihat Finance, General Affairs, Office Assistant
            return Employee::whereIn('department', ['Finance', 'General Affairs', 'Office Assistant'])->get();
        } elseif ($userRole === 'Manager') {
            $userDepartment = $this->department;
            
            // Program Manager
            if (in_array($userDepartment, ['Producer', 'Creative', 'Production', 'Editor'])) {
                return Employee::whereIn('department', ['Producer', 'Creative', 'Production', 'Editor'])
                    ->where('id', '!=', $this->id)->get();
            }
            // Distribution Manager
            elseif (in_array($userDepartment, ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'])) {
                return Employee::whereIn('department', ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'])
                    ->where('id', '!=', $this->id)->get();
            }
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
            // HR bisa approve untuk Finance, General Affairs, Office Assistant
            return in_array($targetEmployee->department, ['Finance', 'General Affairs', 'Office Assistant']);
        } elseif ($userRole === 'Manager') {
            $userDepartment = $this->department;
            
            // Program Manager
            if (in_array($userDepartment, ['Producer', 'Creative', 'Production', 'Editor'])) {
                return in_array($targetEmployee->department, ['Producer', 'Creative', 'Production', 'Editor']);
            }
            // Distribution Manager
            elseif (in_array($userDepartment, ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'])) {
                return in_array($targetEmployee->department, ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care']);
            }
        }
        
        return false;
    }
}