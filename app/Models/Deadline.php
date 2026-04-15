<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Deadline extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'role',
        'deadline_date',
        'is_completed',
        'completed_at',
        'completed_by',
        'notes',
        'status',
        'reminder_sent',
        'reminder_sent_at',
        'description',
        'change_reason',
        'changed_by',
        'changed_at',
        'auto_generated',
        'created_by',
        'assigned_user_id'
    ];

    protected $casts = [
        'deadline_date' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'is_completed' => 'boolean',
        'reminder_sent' => 'boolean',
        'assigned_user_id' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang ditunjuk secara khusus (Backup/Override)
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Accessor: Menghitung sisa hari sampai deadline (Countdown)
     * Format: "H-5" atau "Terlewat 2 hari"
     */
    public function getDaysLeftLabelAttribute(): string
    {
        if ($this->is_completed) return 'Selesai';
        
        $now = now()->startOfDay();
        $target = $this->deadline_date->copy()->startOfDay();
        $diff = $now->diffInDays($target, false);
        
        if ($diff == 0) return 'Hari Ini';
        if ($diff > 0) return 'H-' . $diff;
        return 'Terlewat ' . abs($diff) . ' hari';
    }

    /**
     * Accessor: Raw sisa hari (integer)
     */
    public function getDaysLeftAttribute(): int
    {
        $now = now()->startOfDay();
        $target = $this->deadline_date->copy()->startOfDay();
        return (int) $now->diffInDays($target, false);
    }

    /**
     * Relationship dengan User yang complete
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Relationship dengan User yang mengubah deadline
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Relationship dengan User yang membuat deadline
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mark deadline as completed
     */
    public function markAsCompleted(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId,
            'status' => 'completed',
            'notes' => $notes
        ]);
    }

    /**
     * Check if deadline is overdue
     */
    public function isOverdue(): bool
    {
        return $this->deadline_date < now() && !$this->is_completed;
    }

    /**
     * Check if should send reminder
     */
    public function shouldSendReminder(): bool
    {
        // Send reminder 1 hari sebelum deadline
        $reminderDate = $this->deadline_date->copy()->subDay();
        
        return now() >= $reminderDate && 
               !$this->reminder_sent && 
               !$this->is_completed;
    }

    /**
     * Update status based on date
     */
    public function updateStatus(): void
    {
        if ($this->is_completed) {
            $this->update(['status' => 'completed']);
        } elseif ($this->isOverdue()) {
            $this->update(['status' => 'overdue']);
        } elseif ($this->deadline_date <= now()->addDay()) {
            $this->update(['status' => 'in_progress']);
        }
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'program_manager' => 'Program Manager',
            'manager_distribusi' => 'Distribution Manager',
            'producer' => 'Producer',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'kreatif' => 'Creative',
            'promotion' => 'Promotion',
            'tim_setting_coord' => 'Tim Setting (Coordinator)',
            'tim_syuting_coord' => 'Tim Syuting (Coordinator)',
            'tim_vocal_coord' => 'Tim Rekam Vocal (Coordinator)',
            'general_affairs' => 'General Affairs',
            'art_set_design' => 'Art & Set Properti',
            'editor' => 'Editor',
            'design_grafis' => 'Graphic Design',
            'editor_promosi' => 'Editor Promosi',
            'quality_control' => 'Quality Control',
            'broadcasting' => 'Broadcasting'
        ];

        return $labels[$this->role] ?? ucwords(str_replace('_', ' ', $this->role));
    }

    protected $appends = [
        'role_label',
        'days_left_label',
        'days_left'
    ];

    /**
     * Scope untuk deadline yang overdue
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Scope untuk deadline berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope untuk deadline yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk deadline yang completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }
}
