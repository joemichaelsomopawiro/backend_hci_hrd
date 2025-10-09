<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ProgramRegular extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'program_regular';

    protected $fillable = [
        'name',
        'description',
        'production_team_id',
        'manager_program_id',
        'start_date',
        'air_time',
        'duration_minutes',
        'broadcast_channel',
        'status',
        'target_views_per_episode',
        'submission_notes',
        'submitted_at',
        'submitted_by',
        'approval_notes',
        'approved_by',
        'approved_at',
        'rejection_notes',
        'rejected_by',
        'rejected_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'air_time' => 'datetime:H:i',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'target_views_per_episode' => 'integer'
    ];

    const TOTAL_EPISODES = 53; // Fixed 53 episodes per year

    /**
     * Relasi dengan Production Team
     */
    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    /**
     * Relasi dengan User (Manager Program)
     */
    public function managerProgram(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_program_id');
    }

    /**
     * Relasi dengan User (Submitted By)
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Relasi dengan User (Approved By)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi dengan User (Rejected By)
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Relasi dengan Program Episodes
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(ProgramEpisode::class);
    }

    /**
     * Relasi dengan Program Proposal
     */
    public function proposal(): HasOne
    {
        return $this->hasOne(ProgramProposal::class);
    }

    /**
     * Relasi dengan Program Approvals
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ProgramApproval::class, 'approvable_id')
            ->where('approvable_type', self::class);
    }

    /**
     * Auto-generate 53 episodes when program is created
     * 
     * @return void
     */
    public function generateEpisodes(): void
    {
        $startDate = Carbon::parse($this->start_date);
        $airTime = Carbon::parse($this->air_time)->format('H:i:s');

        for ($episodeNumber = 1; $episodeNumber <= self::TOTAL_EPISODES; $episodeNumber++) {
            // Hitung tanggal tayang (setiap minggu)
            $airDate = $startDate->copy()->addWeeks($episodeNumber - 1);
            $airDateTime = $airDate->format('Y-m-d') . ' ' . $airTime;

            // Buat episode
            $episode = $this->episodes()->create([
                'episode_number' => $episodeNumber,
                'title' => "Episode {$episodeNumber}",
                'air_date' => $airDateTime,
                'status' => 'planning',
                'format_type' => 'weekly'
            ]);

            // Generate deadlines untuk episode ini
            $episode->generateDeadlines();
        }
    }

    /**
     * Get progress percentage (episodes completed / total episodes)
     */
    public function getProgressPercentageAttribute(): float
    {
        $totalEpisodes = $this->episodes()->count();
        if ($totalEpisodes === 0) return 0;

        $airedEpisodes = $this->episodes()->where('status', 'aired')->count();
        
        return round(($airedEpisodes / $totalEpisodes) * 100, 2);
    }

    /**
     * Get upcoming episodes (not aired yet)
     */
    public function upcomingEpisodes()
    {
        return $this->episodes()
            ->where('air_date', '>=', now())
            ->where('status', '!=', 'aired')
            ->orderBy('air_date', 'asc');
    }

    /**
     * Get next episode to air
     */
    public function getNextEpisodeAttribute()
    {
        return $this->upcomingEpisodes()->first();
    }

    /**
     * Check if program is completed (all episodes aired)
     */
    public function isCompleted(): bool
    {
        $totalEpisodes = $this->episodes()->count();
        $airedEpisodes = $this->episodes()->where('status', 'aired')->count();

        return $totalEpisodes > 0 && $totalEpisodes === $airedEpisodes;
    }

    /**
     * Submit program for approval
     */
    public function submitForApproval(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'pending_approval',
            'submitted_by' => $userId,
            'submitted_at' => now(),
            'submission_notes' => $notes
        ]);
    }

    /**
     * Approve program
     */
    public function approve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    /**
     * Reject program
     */
    public function reject(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_notes' => $notes
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
     * Scope: Active programs
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'in_production']);
    }

    /**
     * Scope: By production team
     */
    public function scopeByProductionTeam($query, int $teamId)
    {
        return $query->where('production_team_id', $teamId);
    }
}

