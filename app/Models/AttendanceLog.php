<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_machine_id',
        'user_pin',
        'user_name',
        'card_number',
        'datetime',
        'verified_method',
        'verified_code',
        'status_code',
        'is_processed',
        'raw_data',
    ];

    protected $casts = [
        'datetime' => 'datetime',
        'is_processed' => 'boolean',
    ];

    // Relationships
    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    // Note: employee relationship removed - system now uses user_pin instead of employee_id

    // Scope untuk data yang belum diproses
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    // Scope untuk tanggal tertentu
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('datetime', $date);
    }

    // Scope untuk employee tertentu
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // Method untuk menandai sebagai sudah diproses
    public function markAsProcessed(): void
    {
        $this->update(['is_processed' => true]);
    }

    // Method untuk mendapatkan tanggal saja
    public function getDateAttribute(): string
    {
        return $this->datetime->format('Y-m-d');
    }

    // Method untuk mendapatkan waktu saja
    public function getTimeAttribute(): string
    {
        return $this->datetime->format('H:i:s');
    }

    // Method untuk mapping verified code ke method
    public function getVerifiedMethodFromCode(): string
    {
        // Berdasarkan dokumentasi Solution X304
        // 1 = Password, 15 = Fingerprint, 4 = Card, dll
        switch ($this->verified_code) {
            case 1:
                return 'password';
            case 4:
                return 'card';
            case 15:
                return 'fingerprint';
            case 11:
                return 'face';
            default:
                return 'card'; // default
        }
    }
} 