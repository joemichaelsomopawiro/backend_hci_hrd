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
        'status',
        'approved_at',
        'rejection_reason',
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

    // Removed unused relations: managerApprovedBy and hrApprovedBy
    // Using single 'approved_by' field with 'approver' relation instead

    // Update leave quota when approved
    public function updateLeaveQuota()
    {
        if ($this->status === 'approved') {
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

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
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