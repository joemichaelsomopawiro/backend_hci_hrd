<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'user_id',
        'program_id',
        'episode_id',
        'schedule_id',
        'is_read',
        'read_at',
        'scheduled_at',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'data' => 'array',
    ];

    // Relasi dengan User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi dengan Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Relasi dengan Episode
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    // Relasi dengan Schedule
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    // Scope untuk notifikasi yang belum dibaca
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // Scope untuk notifikasi yang sudah dibaca
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    // Scope untuk notifikasi berdasarkan tipe
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope untuk notifikasi yang terjadwal
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now());
    }

    // Scope untuk notifikasi yang sudah waktunya
    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now())
                    ->where('is_read', false);
    }

    // Method untuk mendapatkan tipe notifikasi
    public function getTypeAttribute($value)
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    // Method untuk menandai notifikasi sebagai sudah dibaca
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    // Method untuk mengecek apakah notifikasi sudah dibaca
    public function isRead()
    {
        return $this->is_read;
    }

    // Method untuk mengecek apakah notifikasi sudah waktunya
    public function isDue()
    {
        return $this->scheduled_at && $this->scheduled_at <= now() && !$this->is_read;
    }

    // Method untuk mendapatkan waktu relatif
    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }
}
