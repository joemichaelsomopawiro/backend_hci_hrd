<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'status',
        'manager_program_id',
        'production_team_id',
        'start_date',
        'air_time',
        'duration_minutes',
        'broadcast_channel',
        'target_views_per_episode',
        'proposal_file_path',
        'proposal_file_name',
        'proposal_file_size',
        'proposal_file_mime_type',
        'submitted_by',
        'submitted_at',
        'submission_notes',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_notes',
        'budget_amount',
        'budget_approved',
        'budget_notes',
        'budget_approved_by',
        'budget_approved_at',
        'total_actual_views',
        'average_views_per_episode',
        'performance_status',
        'last_performance_check',
        'auto_close_enabled',
        'min_episodes_before_evaluation'
    ];

    protected $casts = [
        'start_date' => 'date',
        'air_time' => 'datetime:H:i',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'target_views_per_episode' => 'integer',
        'duration_minutes' => 'integer',
        'proposal_file_size' => 'integer',
        'budget_amount' => 'decimal:2',
        'budget_approved' => 'boolean',
        'budget_approved_at' => 'datetime'
    ];

    /**
     * Relationship dengan Manager Program
     */
    public function managerProgram(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_program_id');
    }

    /**
     * Relationship dengan Production Team
     */
    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    /**
     * Relationship dengan Episodes
     * Exclude soft deleted episodes
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class, 'program_id')->whereNull('episodes.deleted_at');
    }

    // Relasi dengan Team (Many-to-Many through program_team pivot table)
    // Satu program bisa memiliki banyak teams, dan satu team bisa di-assign ke banyak programs
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'program_team')
            ->withTimestamps();
    }

    /**
     * Relationship dengan User yang submit
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Relationship dengan User yang approve
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship dengan User yang reject
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Boot method untuk handle soft delete cascade
     */
    protected static function boot()
    {
        parent::boot();

        // Saat program di-soft delete, soft delete semua episodes juga
        static::deleting(function ($program) {
            if ($program->isForceDeleting()) {
                // Hard delete: cascade akan dihandle oleh database foreign key
                return;
            }

            // Soft delete: manually soft delete semua episodes
            $program->episodes()->each(function ($episode) {
                $episode->delete();
            });
        });

        // Saat program di-restore, restore semua episodes juga
        static::restoring(function ($program) {
            $program->episodes()->onlyTrashed()->each(function ($episode) {
                $episode->restore();
            });
        });
    }

    /**
     * Generate 53 episodes untuk program
     * @param bool $regenerate Jika true, akan hapus episode yang sudah ada dulu
     */
    public function generateEpisodes(bool $regenerate = false): void
    {
        // Check if episodes already exist (exclude soft deleted)
        $existingEpisodes = $this->episodes()->whereNull('deleted_at')->count();
        
        if ($existingEpisodes > 0 && !$regenerate) {
            // Episode sudah ada, tidak perlu generate lagi
            return;
        }
        
        // Jika regenerate, hapus episode yang lama dulu (soft delete)
        if ($regenerate && $existingEpisodes > 0) {
            $this->episodes()->whereNull('deleted_at')->each(function($episode) {
                $episode->delete(); // Soft delete
            });
        }
        
        $startDate = Carbon::parse($this->start_date);
        
        for ($i = 1; $i <= 53; $i++) {
            $airDate = $startDate->copy()->addWeeks($i - 1);
            
            $episode = $this->episodes()->create([
                'episode_number' => $i,
                'title' => "Episode {$i}",
                'description' => "Episode {$i} dari program {$this->name}",
                'air_date' => $airDate,
                'production_date' => $airDate->copy()->subDays(7), // 7 hari sebelum tayang
                'status' => 'draft',
                'current_workflow_state' => 'program_created'
            ]);

            // Generate deadlines untuk episode
            $episode->generateDeadlines();
        }
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        $totalEpisodes = $this->episodes()->count();
        if ($totalEpisodes === 0) return 0;

        $completedEpisodes = $this->episodes()->where('status', 'aired')->count();
        return round(($completedEpisodes / $totalEpisodes) * 100, 2);
    }

    /**
     * Get next episode
     */
    public function getNextEpisodeAttribute(): ?Episode
    {
        return $this->episodes()
            ->where('status', '!=', 'aired')
            ->orderBy('episode_number')
            ->first();
    }

    /**
     * Check if program is completed
     */
    public function isCompleted(): bool
    {
        return $this->episodes()->where('status', '!=', 'aired')->count() === 0;
    }

    /**
     * Scope untuk program yang aktif
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'in_production']);
    }

    /**
     * Scope untuk program berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk program berdasarkan kategori
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope untuk program musik
     */
    public function scopeMusik($query)
    {
        return $query->where('category', 'musik');
    }

    /**
     * Scope untuk program live TV
     */
    public function scopeLiveTv($query)
    {
        return $query->where('category', 'live_tv');
    }
}