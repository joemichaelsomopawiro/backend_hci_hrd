<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_machine_id',
        'sync_type',
        'started_at',
        'completed_at',
        'status',
        'records_processed',
        'records_success',
        'records_failed',
        'error_message',
        'details'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'details' => 'array',
    ];

    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}