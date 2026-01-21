<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PrProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pr_programs';

    protected $fillable = [
        'name',
        'description',
        'manager_program_id',
        'status',
        'start_date',
        'air_time',
        'duration_minutes',
        'broadcast_channel',
        'program_year',
        'producer_id',
        'manager_distribusi_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'air_time' => 'datetime:H:i',
        'program_year' => 'integer'
    ];

    /**
     * Relationship dengan Manager Program
     */
    public function managerProgram(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_program_id');
    }

    /**
     * Relationship dengan Producer
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * Relationship dengan Manager Distribusi
     */
    public function managerDistribusi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_distribusi_id');
    }

    /**
     * Relationship dengan Program Concepts
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(PrProgramConcept::class, 'program_id');
    }

    /**
     * Relationship dengan Revisions
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PrProgramRevision::class, 'program_id');
    }

    /**
     * Relationship dengan Episodes
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(PrEpisode::class, 'program_id');
    }

    /**
     * Relationship dengan Production Schedules
     */
    public function productionSchedules(): HasMany
    {
        return $this->hasMany(PrProductionSchedule::class, 'program_id');
    }

    /**
     * Relationship dengan Program Files
     */
    public function files(): HasMany
    {
        return $this->hasMany(PrProgramFile::class, 'program_id');
    }

    /**
     * Relationship dengan Distribution Schedules
     */
    public function distributionSchedules(): HasMany
    {
        return $this->hasMany(PrDistributionSchedule::class, 'program_id');
    }

    /**
     * Relationship dengan Distribution Reports
     */
    public function distributionReports(): HasMany
    {
        return $this->hasMany(PrDistributionReport::class, 'program_id');
    }

    /**
     * Relationship dengan Team Members (Crews)
     */
    public function crews(): HasMany
    {
        return $this->hasMany(PrProgramCrew::class, 'program_id');
    }

    /**
     * Get current concept (latest approved or pending)
     */
    public function currentConcept()
    {
        return $this->concepts()
            ->whereIn('status', ['pending_approval', 'approved'])
            ->latest()
            ->first();
    }

    /**
     * Auto-generate 53 episodes untuk tahun program
     */
    public function generateEpisodes(): void
    {
        $startDate = Carbon::parse($this->start_date);
        $airTime = Carbon::parse($this->air_time)->format('H:i:s');

        for ($i = 1; $i <= 53; $i++) {
            $airDate = $startDate->copy()->addWeeks($i - 1);

            $this->episodes()->create([
                'episode_number' => $i,
                'title' => "Episode {$i}",
                'description' => "Episode {$i} dari program {$this->name}",
                'air_date' => $airDate,
                'air_time' => $airTime,
                'status' => 'scheduled'
            ]);
        }
    }

    /**
     * Check if program has approved concept
     */
    public function hasApprovedConcept(): bool
    {
        return $this->concepts()
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * Scope untuk program berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk program berdasarkan tahun
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('program_year', $year);
    }

    /**
     * Scope untuk program berdasarkan team membership
     * Filter programs where user is assigned as a crew member
     */
    public function scopeForUser($query, $userId, $role = null)
    {
        return $query->whereHas('crews', function ($q) use ($userId, $role) {
            $q->where('user_id', $userId);
            if ($role) {
                $q->where('role', $role);
            }
        });
    }
}
