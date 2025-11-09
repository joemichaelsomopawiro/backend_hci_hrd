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
        'reminder_sent_at'
    ];

    protected $casts = [
        'deadline_date' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'is_completed' => 'boolean',
        'reminder_sent' => 'boolean'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang complete
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
            'kreatif' => 'Kreatif',
            'musik_arr' => 'Musik Arranger',
            'sound_eng' => 'Sound Engineer',
            'produksi' => 'Produksi',
            'editor' => 'Editor',
            'art_set_design' => 'Art & Set Design',
            'design_grafis' => 'Design Grafis',
            'promotion' => 'Promotion',
            'broadcasting' => 'Broadcasting',
            'quality_control' => 'Quality Control'
        ];

        return $labels[$this->role] ?? $this->role;
    }

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
