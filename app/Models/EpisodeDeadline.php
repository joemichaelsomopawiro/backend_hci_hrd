<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EpisodeDeadline extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_episode_id',
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
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime'
    ];

    /**
     * Relasi dengan Program Episode
     */
    public function programEpisode(): BelongsTo
    {
        return $this->belongsTo(ProgramEpisode::class);
    }

    /**
     * Relasi dengan User (Completed By)
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        return ProductionTeam::ROLE_LABELS[$this->role] ?? $this->role;
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
        return !$this->is_completed && 
               $this->deadline_date < now() && 
               $this->status !== 'cancelled';
    }

    /**
     * Get hours until deadline
     */
    public function getHoursUntilDeadlineAttribute(): int
    {
        return now()->diffInHours($this->deadline_date, false);
    }

    /**
     * Get days until deadline
     */
    public function getDaysUntilDeadlineAttribute(): int
    {
        return now()->diffInDays($this->deadline_date, false);
    }

    /**
     * Check if reminder should be sent (1 day before deadline)
     */
    public function shouldSendReminder(): bool
    {
        if ($this->reminder_sent || $this->is_completed) {
            return false;
        }

        $oneDayBefore = Carbon::parse($this->deadline_date)->subDay();
        
        return now()->greaterThanOrEqualTo($oneDayBefore) && 
               now()->lessThan($this->deadline_date);
    }

    /**
     * Mark reminder as sent
     */
    public function markReminderAsSent(): void
    {
        $this->update([
            'reminder_sent' => true,
            'reminder_sent_at' => now()
        ]);
    }

    /**
     * Auto-update status based on deadline date
     */
    public function updateStatus(): void
    {
        if ($this->is_completed) {
            $this->update(['status' => 'completed']);
        } elseif ($this->deadline_date < now()) {
            $this->update(['status' => 'overdue']);
        } elseif ($this->deadline_date >= now() && $this->deadline_date <= now()->addDays(1)) {
            $this->update(['status' => 'in_progress']);
        } else {
            $this->update(['status' => 'pending']);
        }
    }

    /**
     * Scope: Overdue deadlines
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Scope: Upcoming deadlines (within specified days)
     */
    public function scopeUpcoming($query, int $days = 3)
    {
        return $query->where('deadline_date', '>=', now())
            ->where('deadline_date', '<=', now()->addDays($days))
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Scope: Pending deadlines
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false)
            ->where('status', 'pending');
    }

    /**
     * Scope: By role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}

