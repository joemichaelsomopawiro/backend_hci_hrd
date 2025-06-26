<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'comm_key',
        'soap_port',
        'device_id',
        'serial_number',
        'status',
        'last_sync_at',
        'settings',
        'description'
    ];

    protected $casts = [
        'settings' => 'array',
        'last_sync_at' => 'datetime',
        'port' => 'integer',
    ];

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AttendanceSyncLog::class);
    }

    public function machineUsers(): HasMany
    {
        return $this->hasMany(AttendanceMachineUser::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getConnectionUrl(): string
    {
        return "http://{$this->ip_address}:{$this->soap_port}/iWsService";
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getLastSyncStatus(): ?string
    {
        $lastLog = $this->syncLogs()->latest('started_at')->first();
        return $lastLog ? $lastLog->status : null;
    }
}