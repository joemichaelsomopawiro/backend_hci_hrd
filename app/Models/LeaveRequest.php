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
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'notes',
        // Remove old fields
        // 'status',
        // 'approved_by',
        // 'approved_at',
        // 'rejection_reason',
        
        // Add new hierarchy fields
        'manager_approved_by',
        'manager_status',
        'manager_approved_at',
        'manager_rejection_reason',
        'hr_approved_by',
        'hr_status',
        'hr_approved_at',
        'hr_rejection_reason',
        'overall_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function managerApprovedBy()
    {
        return $this->belongsTo(Employee::class, 'manager_approved_by');
    }

    public function hrApprovedBy()
    {
        return $this->belongsTo(Employee::class, 'hr_approved_by');
    }

    // Update leave quota when fully approved
    public function updateLeaveQuota()
    {
        if ($this->overall_status === 'approved') {
            $quota = $this->employee->getCurrentLeaveQuota();
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

    // Hitung total hari kerja (exclude weekend)
    public function calculateWorkingDays()
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        
        $workingDays = 0;
        while ($start->lte($end)) {
            if (!$start->isWeekend()) {
                $workingDays++;
            }
            $start->addDay();
        }
        
        return $workingDays;
    }
}