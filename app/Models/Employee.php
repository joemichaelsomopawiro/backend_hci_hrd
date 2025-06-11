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
}