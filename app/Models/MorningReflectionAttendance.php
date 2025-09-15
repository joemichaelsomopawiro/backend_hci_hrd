<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MorningReflectionAttendance extends Model
{
    use HasFactory;

    protected $table = 'morning_reflection_attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'join_time',
        'testing_mode',
        'attendance_method',
        'attendance_source'
    ];

    protected $casts = [
        'date' => 'date',
        'join_time' => 'datetime',
        'testing_mode' => 'boolean'
    ];

    // Constants untuk status yang valid
    const STATUS_HADIR = 'Hadir';
    const STATUS_TERLAMBAT = 'Terlambat';
    const STATUS_ABSEN = 'Absen';
    const STATUS_IZIN = 'izin';
    const STATUS_CUTI = 'Cuti';

    public static function getValidStatuses()
    {
        return [
            self::STATUS_HADIR,
            self::STATUS_TERLAMBAT,
            self::STATUS_ABSEN,
            self::STATUS_IZIN,
            self::STATUS_CUTI
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope untuk filter berdasarkan metode absensi
     */
    public function scopeByAttendanceMethod($query, $method)
    {
        return $query->where('attendance_method', $method);
    }

    /**
     * Scope untuk filter berdasarkan sumber absensi
     */
    public function scopeByAttendanceSource($query, $source)
    {
        return $query->where('attendance_source', $source);
    }

    /**
     * Scope untuk data manual input
     */
    public function scopeManualInput($query)
    {
        return $query->where('attendance_method', 'manual')
                    ->where('attendance_source', 'manual_input');
    }

    /**
     * Scope untuk data online/zoom
     */
    public function scopeOnline($query)
    {
        return $query->where('attendance_method', 'online')
                    ->where('attendance_source', 'zoom');
    }
}