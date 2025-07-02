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
        'operation',
        'status',
        'message',
        'details',
        'records_processed',
        'started_at',
        'completed_at',
        'duration',
    ];

    protected $casts = [
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration' => 'decimal:3',
    ];

    // Relationships
    public function attendanceMachine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class);
    }

    // Scope untuk operasi tertentu
    public function scopeForOperation($query, $operation)
    {
        return $query->where('operation', $operation);
    }

    // Scope untuk status tertentu
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    // Method untuk menandai operasi selesai
    public function markCompleted(string $status, string $message = null, array $details = null): void
    {
        $this->update([
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'completed_at' => now(),
            'duration' => $this->started_at ? now()->diffInSeconds($this->started_at) : 0,
        ]);
    }

    // Method untuk menambah jumlah record yang diproses
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('records_processed', $count);
    }

    // Accessor untuk label operasi
    public function getOperationLabelAttribute(): string
    {
        $labels = [
            'pull_data' => 'Tarik Data Absensi',
            'push_user' => 'Upload User ke Mesin',
            'delete_user' => 'Hapus User dari Mesin',
            'clear_data' => 'Hapus Data Mesin',
            'sync_time' => 'Sinkronisasi Waktu',
            'restart_machine' => 'Restart Mesin',
            'test_connection' => 'Test Koneksi'
        ];

        return $labels[$this->operation] ?? $this->operation;
    }

    // Accessor untuk label status
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'success' => 'Berhasil',
            'failed' => 'Gagal',
            'partial' => 'Sebagian Berhasil'
        ];

        return $labels[$this->status] ?? $this->status;
    }
} 