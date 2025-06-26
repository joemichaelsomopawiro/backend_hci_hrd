<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'work_hours',
        'overtime_hours',
        'notes',
        'attendance_machine_id',
        'machine_log_id',
        'source',
        'machine_timestamp'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'work_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'machine_timestamp' => 'datetime'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    public function isFromMachine(): bool
    {
        return $this->source === 'machine';
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function isFromWeb(): bool
    {
        return $this->source === 'web';
    }

    public function scopeFromMachine($query)
    {
        return $query->where('source', 'machine');
    }

    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    // Hitung jam kerja otomatis
    public function calculateWorkHours()
    {
        if ($this->check_in && $this->check_out) {
            $checkIn = Carbon::parse($this->check_in);
            $checkOut = Carbon::parse($this->check_out);
            
            $totalMinutes = $checkOut->diffInMinutes($checkIn);
            $workHours = $totalMinutes / 60;
            
            // Kurangi jam istirahat (1 jam)
            if ($workHours > 4) {
                $workHours -= 1;
            }
            
            return round($workHours, 2);
        }
        
        return 0;
    }

    // Hitung jam lembur
    public function calculateOvertimeHours()
    {
        $standardWorkHours = 8;
        $actualWorkHours = $this->work_hours ?? $this->calculateWorkHours();
        
        return max(0, $actualWorkHours - $standardWorkHours);
    }
}