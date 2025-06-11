<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
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