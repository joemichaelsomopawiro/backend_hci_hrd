<?php 

namespace App\Models; 

use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Model; 
use Carbon\Carbon; 

class LeaveRequest extends Model 
{ 
    use HasFactory; 

    protected $fillable = [ 
        'employee_id', 
        'approved_by', 
        'leave_type', 
        'start_date', 
        'end_date', 
        'total_days', 
        'reason', 
        'notes', 
        'overall_status', 
        'approved_at', 
        'rejection_reason', 
        'employee_signature_path',
        'approver_signature_path',
        'leave_location',
        'contact_phone',
    ]; 

    protected $casts = [ 
        'start_date' => 'date', 
        'end_date' => 'date', 
        'approved_at' => 'datetime', 
    ]; 

    public function employee() 
    { 
        return $this->belongsTo(Employee::class, 'employee_id'); 
    } 

    public function approvedBy() 
    { 
        return $this->belongsTo(Employee::class, 'approved_by'); 
    } 

    // Relasi ini mungkin tidak lagi relevan dengan alur yang disederhanakan,
    // tapi tidak masalah untuk tetap ada.
    public function managerApprovedBy() 
    { 
        return $this->belongsTo(Employee::class, 'manager_approved_by'); 
    } 

    public function hrApprovedBy() 
    { 
        return $this->belongsTo(Employee::class, 'hr_approved_by'); 
    } 

    // Update leave quota when approved 
    public function updateLeaveQuota() 
    { 
        if ($this->overall_status === 'approved') {
            // Gunakan tahun dari tanggal mulai cuti, bukan tahun berjalan
            $year = Carbon::parse($this->start_date)->year;
            $quota = $this->employee->getLeaveQuotaForYear($year); 
            if ($quota) { 
                switch ($this->leave_type) { 
                    case 'annual': 
                        $quota->annual_leave_used += $this->total_days; 
                        break; 
                    case 'sick': 
                        $quota->sick_leave_used += $this->total_days; 
                        break; 
                    case 'emergency': 
                        $quota->emergency_leave_used += $this->total_days; 
                        break; 
                    case 'maternity': 
                        $quota->maternity_leave_used += $this->total_days; 
                        break; 
                    case 'paternity': 
                        $quota->paternity_leave_used += $this->total_days; 
                        break; 
                    case 'marriage': 
                        $quota->marriage_leave_used += $this->total_days; 
                        break; 
                    case 'bereavement': 
                        $quota->bereavement_leave_used += $this->total_days; 
                        break; 
                } 
                $quota->save(); 
            } 
        } 
    } 

    // Get status badge color 
    public function getStatusBadgeAttribute() 
    { 
        return match($this->overall_status) { // Menggunakan overall_status
            'pending' => 'warning', 
            'approved' => 'success', 
            'rejected' => 'danger', 
            'expired' => 'dark',
            default => 'secondary' 
        }; 
    }

    // Check if leave request is expired
    public function isExpired(): bool
    {
        return $this->overall_status === 'expired';
    }

    // Check if leave request can be processed (approved/rejected)
    public function canBeProcessed(): bool
    {
        return $this->overall_status === 'pending';
    }

    // Auto-expire if start date has passed
    public function checkAndExpire(): bool
    {
        if ($this->overall_status === 'pending' && $this->start_date < now()->toDateString()) {
            $this->update([
                'overall_status' => 'expired',
                'rejection_reason' => 'Permohonan cuti otomatis expired karena sudah melewati tanggal mulai cuti tanpa persetujuan.'
            ]);
            return true;
        }
        return false;
    } 
}