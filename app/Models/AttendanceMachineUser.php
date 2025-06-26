<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMachineUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_machine_id',
        'employee_id',
        'badge_number',
        'user_name',
        'sync_status',
        'last_sync_at',
        'machine_user_id'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isSynced(): bool
    {
        return $this->sync_status === 'synced';
    }
}