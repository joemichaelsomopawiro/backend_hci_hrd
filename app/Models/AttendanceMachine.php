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
        'device_id',
        'serial_number',
        'status',
        'last_sync_at',
        'settings',
        'description',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'settings' => 'array',
    ];

    // Relationships
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AttendanceSyncLog::class);
    }

    // Scope untuk mesin aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Method untuk test koneksi
    public function testConnection(): bool
    {
        $connect = @fsockopen($this->ip_address, $this->port, $errno, $errstr, 5);
        if ($connect) {
            fclose($connect);
            return true;
        }
        return false;
    }

    // Method untuk mendapatkan URL SOAP Web Service
    public function getSoapUrl(): string
    {
        return "http://{$this->ip_address}:{$this->port}/iWsService";
    }

    // Method untuk update last sync
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }
}