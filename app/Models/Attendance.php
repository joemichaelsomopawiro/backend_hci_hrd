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
        'user_pin',
        'user_name',
        'card_number',
        'date',
        'check_in',
        'check_out',
        'status',
        'work_hours',
        'overtime_hours',
        'late_minutes',
        'early_leave_minutes',
        'total_taps',
        'notes',
        'employee_id', // Tambahkan ini untuk auto-sync dengan Employee
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'work_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // Method untuk mendapatkan work schedule dari environment
    public static function getWorkStartTime(): string
    {
        return env('ATTENDANCE_WORK_START_TIME', '07:30:00');
    }

    public static function getWorkEndTime(): string
    {
        return env('ATTENDANCE_WORK_END_TIME', '16:30:00');
    }

    public static function getLunchBreakDuration(): int
    {
        return env('ATTENDANCE_LUNCH_BREAK_DURATION', 60); // dalam menit
    }

    public static function getOvertimeStartTime(): string
    {
        return env('ATTENDANCE_OVERTIME_START_TIME', '16:30:00');
    }

    // Relasi ke EmployeeAttendance berdasarkan user_pin
    public function employeeAttendance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendance::class, 'user_pin', 'machine_user_id');
    }

    // Relasi ke Employee berdasarkan employee_id untuk auto-sync
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Note: employee relationship removed - system now uses user_pin instead of employee_id

    // Scope untuk tanggal tertentu
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    // Scope untuk bulan tertentu
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)
                    ->whereMonth('date', $month);
    }

    // Scope untuk status tertentu
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Method untuk menghitung jam kerja
    public function calculateWorkHours(): float
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }

        $checkIn = Carbon::parse($this->check_in);
        $checkOut = Carbon::parse($this->check_out);
        $dateOnly = Carbon::parse($this->date)->format('Y-m-d');
        
        // Jam kerja dimulai dari jam 6 pagi
        $workStartTime = Carbon::parse($dateOnly . ' 06:00:00');
        
        // Jika check-in sebelum jam 6 pagi, gunakan jam 6 pagi sebagai waktu mulai kerja
        if ($checkIn->lt($workStartTime)) {
            $checkIn = $workStartTime;
        }
        
        // Hitung selisih dalam jam dari waktu mulai kerja yang sudah disesuaikan
        $totalHours = $checkOut->diffInMinutes($checkIn) / 60;
        
        // Tidak ada pengurangan lunch break - jam kerja murni dari jam 6 pagi sampai check-out

        return round($totalHours, 2);
    }

    // Method untuk menghitung keterlambatan
    public function calculateLateMinutes(): int
    {
        if (!$this->check_in) {
            return 0;
        }

        $checkIn = Carbon::parse($this->check_in);
        $dateOnly = Carbon::parse($this->date)->format('Y-m-d');
        $workStart = Carbon::parse($dateOnly . ' ' . self::getWorkStartTime());
        
        if ($checkIn->gt($workStart)) {
            return $checkIn->diffInMinutes($workStart);
        }

        return 0;
    }

    // Method untuk menghitung pulang cepat
    public function calculateEarlyLeaveMinutes(): int
    {
        if (!$this->check_out) {
            return 0;
        }

        $checkOut = Carbon::parse($this->check_out);
        $dateOnly = Carbon::parse($this->date)->format('Y-m-d');
        $workEnd = Carbon::parse($dateOnly . ' ' . self::getWorkEndTime());
        
        if ($checkOut->lt($workEnd)) {
            return $workEnd->diffInMinutes($checkOut);
        }

        return 0;
    }

    // Method untuk menghitung lembur
    public function calculateOvertimeHours(): float
    {
        if (!$this->check_out) {
            return 0;
        }

        $checkOut = Carbon::parse($this->check_out);
        $dateOnly = Carbon::parse($this->date)->format('Y-m-d');
        $overtimeStart = Carbon::parse($dateOnly . ' ' . self::getOvertimeStartTime());
        
        if ($checkOut->gt($overtimeStart)) {
            return round($checkOut->diffInMinutes($overtimeStart) / 60, 2);
        }

        return 0;
    }

    // Method untuk menentukan status berdasarkan waktu check-in
    public function determineStatus(): string
    {
        // Note: Leave request checking removed since system is now independent from employees table
        // For leave management, need to implement separate PIN-based leave system if needed
        
        // Jika tidak ada check-in sama sekali
        if (!$this->check_in) {
            return 'absent';
        }

        // Jika ada check-in, tentukan apakah terlambat atau tidak
        $lateMinutes = $this->calculateLateMinutes();
        
        if ($lateMinutes > 0) {
            return 'present_late';
        } else {
            return 'present_ontime';
        }
    }

    // Method untuk update semua kalkulasi
    public function updateCalculations(): void
    {
        $this->work_hours = $this->calculateWorkHours();
        $this->overtime_hours = $this->calculateOvertimeHours();
        $this->late_minutes = $this->calculateLateMinutes();
        $this->early_leave_minutes = $this->calculateEarlyLeaveMinutes();
        $this->status = $this->determineStatus();
        $this->save();
    }

    // Accessor untuk format waktu
    public function getCheckInTimeAttribute(): ?string
    {
        return $this->check_in ? $this->check_in->format('H:i:s') : null;
    }

    public function getCheckOutTimeAttribute(): ?string
    {
        return $this->check_out ? $this->check_out->format('H:i:s') : null;
    }

    // Method untuk mendapatkan status dalam bahasa Indonesia
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'present_ontime' => 'Hadir Tepat Waktu',
            'present_late' => 'Hadir Terlambat',
            'absent' => 'Tidak Hadir',
            'on_leave' => 'Cuti',
            'sick_leave' => 'Sakit',
            'permission' => 'Izin'
        ];

        return $labels[$this->status] ?? 'Unknown';
    }
}