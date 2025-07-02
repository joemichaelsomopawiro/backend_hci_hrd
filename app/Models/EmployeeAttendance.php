<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = [
        'attendance_machine_id',
        'machine_user_id',
        'name',
        'card_number',
        'department',
        'privilege',
        'group_name',
        'is_active',
        'raw_data',
        'last_seen_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByMachine($query, $machineId)
    {
        return $query->where('attendance_machine_id', $machineId);
    }

    public function scopeByUserIds($query, array $userIds)
    {
        return $query->whereIn('machine_user_id', $userIds);
    }

    // Helper methods
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "User_{$this->machine_user_id}";
    }

    public function hasCardNumber(): bool
    {
        return !empty($this->card_number) && $this->card_number !== '0';
    }

    public function getPrivilegeLabelAttribute(): string
    {
        $labels = [
            'Super Administrator' => 'Super Admin',
            'Administrator' => 'Admin', 
            'User' => 'User',
        ];

        return $labels[$this->privilege] ?? $this->privilege;
    }
}
