<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ProgramEpisode extends Model
{
    use HasFactory;

    protected $table = 'program_episodes';

    protected $fillable = [
        'program_regular_id',
        'episode_number',
        'title',
        'description',
        'air_date',
        'production_date',
        'format_type',
        'kwartal',
        'pelajaran',
        'status',
        'rundown',
        'script',
        'talent_data',
        'location',
        'notes',
        'production_notes'
    ];

    protected $casts = [
        'air_date' => 'datetime',
        'production_date' => 'date',
        'talent_data' => 'array',
        'production_notes' => 'array'
    ];

    /**
     * Relasi dengan Program Regular
     */
    public function programRegular(): BelongsTo
    {
        return $this->belongsTo(ProgramRegular::class);
    }

    /**
     * Relasi dengan Episode Deadlines
     */
    public function deadlines(): HasMany
    {
        return $this->hasMany(EpisodeDeadline::class);
    }

    /**
     * Relasi dengan Approvals (Polymorphic)
     */
    public function approvals()
    {
        return $this->morphMany(ProgramApproval::class, 'approvable');
    }

    /**
     * Auto-generate deadlines untuk episode ini
     * 
     * Aturan:
     * - Editor: 7 hari sebelum tayang
     * - Kreatif & Produksi: 9 hari sebelum tayang
     * - Musik Arr, Sound Eng, Art Set Design: 9 hari sebelum tayang (ikut kreatif)
     */
    public function generateDeadlines(): void
    {
        $airDate = Carbon::parse($this->air_date);
        
        // Deadline roles mapping
        $deadlineRoles = [
            'editor' => 7,              // 7 hari sebelum tayang
            'kreatif' => 9,             // 9 hari sebelum tayang
            'musik_arr' => 9,           // 9 hari sebelum tayang
            'sound_eng' => 9,           // 9 hari sebelum tayang
            'produksi' => 9,            // 9 hari sebelum tayang
            'art_set_design' => 9       // 9 hari sebelum tayang
        ];

        foreach ($deadlineRoles as $role => $daysBefore) {
            $deadlineDate = $airDate->copy()->subDays($daysBefore);

            $this->deadlines()->create([
                'role' => $role,
                'deadline_date' => $deadlineDate,
                'status' => 'pending',
                'is_completed' => false
            ]);
        }
    }

    /**
     * Get deadline for specific role
     */
    public function getDeadlineForRole(string $role)
    {
        return $this->deadlines()->where('role', $role)->first();
    }

    /**
     * Get overdue deadlines
     */
    public function getOverdueDeadlines()
    {
        return $this->deadlines()
            ->where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->get();
    }

    /**
     * Get upcoming deadlines (within 3 days)
     */
    public function getUpcomingDeadlines()
    {
        return $this->deadlines()
            ->where('deadline_date', '>=', now())
            ->where('deadline_date', '<=', now()->addDays(3))
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->orderBy('deadline_date', 'asc')
            ->get();
    }

    /**
     * Check if all deadlines are completed
     */
    public function allDeadlinesCompleted(): bool
    {
        $totalDeadlines = $this->deadlines()->count();
        $completedDeadlines = $this->deadlines()->where('is_completed', true)->count();

        return $totalDeadlines > 0 && $totalDeadlines === $completedDeadlines;
    }

    /**
     * Get days until air date
     */
    public function getDaysUntilAirAttribute(): int
    {
        return now()->diffInDays($this->air_date, false);
    }

    /**
     * Check if episode is overdue
     */
    public function isOverdue(): bool
    {
        return $this->air_date < now() && $this->status !== 'aired';
    }

    /**
     * Get episode progress percentage based on deadlines
     */
    public function getProgressPercentageAttribute(): float
    {
        $totalDeadlines = $this->deadlines()->count();
        if ($totalDeadlines === 0) return 0;

        $completedDeadlines = $this->deadlines()->where('is_completed', true)->count();
        
        return round(($completedDeadlines / $totalDeadlines) * 100, 2);
    }

    /**
     * Submit rundown for approval
     */
    public function submitRundown(int $userId, ?string $notes = null): ProgramApproval
    {
        return $this->approvals()->create([
            'approval_type' => 'episode_rundown',
            'requested_by' => $userId,
            'requested_at' => now(),
            'request_notes' => $notes,
            'status' => 'pending'
        ]);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Upcoming episodes
     */
    public function scopeUpcoming($query)
    {
        return $query->where('air_date', '>=', now())
            ->where('status', '!=', 'aired')
            ->orderBy('air_date', 'asc');
    }

    /**
     * Scope: Aired episodes
     */
    public function scopeAired($query)
    {
        return $query->where('status', 'aired')
            ->orderBy('air_date', 'desc');
    }

    /**
     * Scope: Overdue episodes
     */
    public function scopeOverdue($query)
    {
        return $query->where('air_date', '<', now())
            ->where('status', '!=', 'aired');
    }
}

