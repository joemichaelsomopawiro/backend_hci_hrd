<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_lengkap',
        'nik',
        'nip',
        'NumCard',
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
        'created_from',
    ];

    // Cast jabatan_saat_ini sebagai enum untuk validasi
    protected $casts = [
        'jabatan_saat_ini' => 'string',
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
    
    // Relationship untuk attendance system  
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    /**
     * Relasi untuk mendapatkan data absensi HARI INI saja.
     */
    public function todaysAttendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->whereDate('check_in', today());
    }

    /**
     * Relasi untuk mendapatkan data cuti yang disetujui dan aktif HARI INI.
     */
    public function approvedLeaveForToday(): HasOne
    {
        return $this->hasOne(LeaveRequest::class)
                    ->where('overall_status', 'approved')
                    ->where('start_date', '<=', today())
                    ->where('end_date', '>=', today());
    }

    // Method untuk mendapatkan semua bawahan berdasarkan role
    public function getSubordinatesByRole()
    {
        $userRole = $this->user->role ?? null;
        
        if (!\App\Services\RoleHierarchyService::isManager($userRole)) {
            return collect();
        }
        
        $subordinateRoles = \App\Services\RoleHierarchyService::getSubordinateRoles($userRole);
        
        return Employee::whereHas('user', function($query) use ($subordinateRoles) {
            $query->whereIn('role', $subordinateRoles);
        })->where('id', '!=', $this->id)->get();
    }
    
    // Alias method untuk konsistensi
    public function getSubordinatesByDepartment()
    {
        return $this->getSubordinatesByRole();
    }

    // Method untuk cek apakah user bisa approve leave request
    public function canApproveLeaveFor($employeeId)
    {
        $userRole = $this->user->role ?? null;
        $targetEmployee = Employee::find($employeeId);
        
        if (!$targetEmployee || !$targetEmployee->user) return false;
        
        $targetEmployeeRole = $targetEmployee->user->role;
        
        // Gunakan RoleHierarchyService untuk cek approval
        return \App\Services\RoleHierarchyService::canApproveLeave($userRole, $targetEmployeeRole);
    }

    // Get current year leave quota
    public function getCurrentLeaveQuota()
    {
        return $this->leaveQuotas()->where('year', date('Y'))->first();
    }

    // Get leave quota for specific year
    public function getLeaveQuotaForYear($year)
    {
        return $this->leaveQuotas()->where('year', $year)->first();
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
            case 'maternity':
                return ($quota->maternity_leave_used + $days) <= $quota->maternity_leave_quota;
            case 'paternity':
                return ($quota->paternity_leave_used + $days) <= $quota->paternity_leave_quota;
            case 'marriage':
                return ($quota->marriage_leave_used + $days) <= $quota->marriage_leave_quota;
            case 'bereavement':
                return ($quota->bereavement_leave_used + $days) <= $quota->bereavement_leave_quota;
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
                case 'maternity':
                    $quota->maternity_leave_used += $days;
                    break;
                case 'paternity':
                    $quota->paternity_leave_used += $days;
                    break;
                case 'marriage':
                    $quota->marriage_leave_used += $days;
                    break;
                case 'bereavement':
                    $quota->bereavement_leave_used += $days;
                    break;
            }
            $quota->save();
        }
    }

    public function machineUsers(): HasMany
    {
        return $this->hasMany(AttendanceMachineUser::class);
    }

    public function isSyncedToMachine(AttendanceMachine $machine): bool
    {
        return $this->machineUsers()
            ->where('attendance_machine_id', $machine->id)
            ->where('status', 'synced')
            ->exists();
    }

    public function getMachineUser(AttendanceMachine $machine): ?AttendanceMachineUser
    {
        return $this->machineUsers()
            ->where('attendance_machine_id', $machine->id)
            ->first();
    }

    public function employeeAttendance(): HasOne
    {
        return $this->hasOne(EmployeeAttendance::class);
    }

    /**
     * Accessor untuk mendapatkan status kehadiran karyawan saat ini.
     * Ini akan bisa diakses seperti kolom biasa: $employee->current_status
     */
    public function getCurrentStatusAttribute(): string
    {
        // Prioritas 1: Cek apakah karyawan sedang cuti
        if ($this->approvedLeaveForToday) {
            return 'Cuti';
        }

        // Prioritas 2: Cek apakah karyawan sudah check-in hari ini
        if ($this->todaysAttendance) {
            return 'Hadir';
        }

        // Jika tidak keduanya, maka dianggap absen
        return 'Absen';
    }
}