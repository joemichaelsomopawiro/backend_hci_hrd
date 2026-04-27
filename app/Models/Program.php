<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'genre',
        'target_audience',
        'status',
        'manager_program_id',
        'production_team_id',
        'start_date',
        'air_time',
        'duration_minutes',
        'broadcast_channel',
        'target_views_per_episode',
        'target_audience',           // Added target audience field
        'proposal_file_link',        // New: External storage link for proposal
        'proposal_file_path',        // Kept for backward compatibility
        'proposal_file_name',        // Kept for backward compatibility
        'proposal_file_size',        // Kept for backward compatibility
        'proposal_file_mime_type',   // Kept for backward compatibility
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
        'max_budget_per_episode',
        'budget_approved',
        'budget_notes',
        'budget_approved_by',
        'budget_approved_at',
        'total_actual_views',
        'average_views_per_episode',
        'performance_status',
        'last_performance_check',
        'auto_close_enabled',
        'min_episodes_before_evaluation',
        // Producer Acceptance
        'producer_accepted',
        'producer_accepted_by',
        'producer_accepted_at',
        'producer_rejected_at',
        'producer_rejection_notes',
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
        'max_budget_per_episode' => 'decimal:2',
        'budget_approved' => 'boolean',
        'budget_approved_at' => 'datetime',
        'producer_accepted' => 'boolean',
        'producer_accepted_at' => 'datetime',
        'producer_rejected_at' => 'datetime',
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
     * Relationship dengan User yang menerima (Producer)
     */
    public function producerAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_accepted_by');
    }

    /**
     * Relationship dengan User yang reject
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Scope: Filter by Music Programs only
     * Usage: Program::musik()->get()
     */
    public function scopeMusik($query)
    {
        return $query->where('category', 'musik');
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
     * Generate 53 episodes untuk program (1 tahun = 52-53 minggu)
     * Episode 1 = Sabtu pertama di tahun program
     * Setiap episode tayang setiap Sabtu (mingguan)
     * @param bool $regenerate Jika true, akan hapus episode yang sudah ada dulu
     */
    public function generateEpisodes(bool $regenerate = false): void
    {
        // Check if episodes already exist (exclude soft deleted)
        $existingEpisodesCount = $this->episodes()->whereNull('deleted_at')->count();

        if ($existingEpisodesCount > 0 && !$regenerate) {
            return;
        }

        // Jika regenerate, hapus episode yang lama dulu (soft delete)
        if ($regenerate && $existingEpisodesCount > 0) {
            $this->episodes()->whereNull('deleted_at')->each(function ($episode) {
                $episode->delete();
            });
        }

        // Siapkan air_time
        $airTime = $this->air_time;
        if ($airTime instanceof \Carbon\Carbon) {
            $hour = $airTime->hour;
            $minute = $airTime->minute;
        } else {
            $timeParts = explode(':', (string)($airTime ?? '00:00'));
            $hour = isset($timeParts[0]) ? (int)$timeParts[0] : 0;
            $minute = isset($timeParts[1]) ? (int)$timeParts[1] : 0;
        }

        $startDate = Carbon::parse($this->start_date)->setTime($hour, $minute, 0);
        $targetDayOfWeek = $startDate->dayOfWeek;
        $year = $startDate->year;
        
        // Episode 1 dimulai tepat pada start_date (dengan air_time)
        $firstOccurrence = $startDate->copy();

        $episodesToInsert = [];
        $currentUserId = auth()->id() ?? 1;

        // Generate 53 episode data
        for ($i = 1; $i <= 53; $i++) {
            $airDate = $firstOccurrence->copy();
            if ($i > 1) {
                $airDate->addWeeks($i - 1);
            }
            // Gunakan air_time yang sudah di-set di firstOccurrence
            $productionDate = $airDate->copy()->subDays(7);
            $productionDate->utc()->setTime(0, 0, 0);

            $episodesToInsert[] = [
                'program_id' => $this->id,
                'episode_number' => $i,
                'title' => "Episode {$i}",
                'description' => "Episode {$i} dari program {$this->name}",
                'air_date' => $airDate->format('Y-m-d H:i:s'),
                'production_date' => $productionDate->format('Y-m-d H:i:s'),
                'production_deadline' => $productionDate->format('Y-m-d H:i:s'),
                'status' => 'draft',
                'current_workflow_state' => 'program_created',
                'format_type' => 'weekly',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Bulk insert episodes and get IDs
        // Note: insertGetId doesn't support bulk. We use insert and then fetch IDs or insert one by one but in transaction.
        // For performance, we'll do individual inserts for episodes to get IDs, but bulk for deadlines.
        // Even individual inserts for episodes (52) is much faster than N+1 deadlines (400+).
        
            // Insert deadlines
            $category = $this->category ?? 'regular';
            $isMusik = strtolower($category) === 'musik';
            $rolesList = $isMusik ? \App\Constants\WorkflowStep::MUSIC_ROLE_DEADLINE_DAYS : \App\Constants\WorkflowStep::ROLE_DEADLINE_DAYS;
            
            \DB::transaction(function() use ($episodesToInsert, &$deadlinesToInsert, $category, $currentUserId, $rolesList) {
                foreach ($episodesToInsert as $episodeData) {
                    $airDate = Carbon::parse($episodeData['air_date']);
                    $episodeId = \DB::table('episodes')->insertGetId($episodeData);
                    
                    // Generate deadlines for ALL roles dynamically
                    foreach (array_keys($rolesList) as $role) {
                        $deadlinesToInsert[] = [
                            'episode_id' => $episodeId,
                            'role' => $role,
                            'deadline_date' => $airDate->copy()->subDays(\App\Constants\WorkflowStep::getDeadlineDaysForRole($role, $category))->format('Y-m-d H:i:s'),
                            'description' => 'Deadline for ' . $role,
                            'auto_generated' => true,
                            'created_by' => $currentUserId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
                
                // Bulk insert all deadlines in chunks of 500 to avoid query size limits
                if (!empty($deadlinesToInsert)) {
                    $chunks = array_chunk($deadlinesToInsert, 500);
                    foreach ($chunks as $chunk) {
                        \DB::table('deadlines')->insert($chunk);
                    }
                }
            });
    }

    /**
     * Generate 53 episodes untuk tahun pertama program
     * Dipanggil saat program pertama kali dibuat
     * OPTIMIZED: Bulk insert deadlines to avoid N+1 (212 queries → 54 queries)
     */
    public function generateYearlyEpisodes()
    {
        // Siapkan air_time
        $airTime = $this->air_time;
        if ($airTime instanceof \Carbon\Carbon) {
            $hour = $airTime->hour;
            $minute = $airTime->minute;
        } else {
            $timeParts = explode(':', (string)($airTime ?? '00:00'));
            $hour = isset($timeParts[0]) ? (int)$timeParts[0] : 0;
            $minute = isset($timeParts[1]) ? (int)$timeParts[1] : 0;
        }

        // Episode 1 dimulai tepat pada start_date (dengan air_time)
        $firstOccurrence = Carbon::parse($this->start_date)->setTime($hour, $minute, 0);

        $deadlinesToInsert = [];
        $currentUserId = auth()->id() ?? 1;

        // Generate 53 episode (1 tahun = 52-53 minggu)
        for ($i = 1; $i <= 53; $i++) {
            // Episode 1 = start_date, Episode 2 = 7 hari kemudian, dst
            $airDate = $firstOccurrence->copy();
            if ($i > 1) {
                $airDate->addWeeks($i - 1);
            }
            // Production date = 7 hari sebelum tayang
            $productionDate = $airDate->copy()->subDays(7);
            $productionDate->utc()->setTime(0, 0, 0);

            // Format untuk insert
            $airDateStr = $airDate->format('Y-m-d H:i:s');
            $productionDateStr = $productionDate->format('Y-m-d H:i:s');

            // Insert episode
            $episodeId = \DB::table('episodes')->insertGetId([
                'program_id' => $this->id,
                'episode_number' => $i,
                'title' => "Episode {$i}",
                'description' => "Episode {$i} dari program {$this->name}",
                'air_date' => \DB::raw("'{$airDateStr}'"),
                'production_date' => \DB::raw("'{$productionDateStr}'"),
                'production_deadline' => \DB::raw("'{$productionDateStr}'"),
                'status' => 'draft',
                'current_workflow_state' => 'program_created',
                'format_type' => 'weekly',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // OPTIMIZED: Collect deadlines for bulk insert using WorkflowStep constants
            $category = $this->category ?? 'regular';
            $isMusik = strtolower($category) === 'musik';
            $rolesList = $isMusik ? \App\Constants\WorkflowStep::MUSIC_ROLE_DEADLINE_DAYS : \App\Constants\WorkflowStep::ROLE_DEADLINE_DAYS;

            foreach (array_keys($rolesList) as $role) {
                $deadlinesToInsert[] = [
                    'episode_id' => $episodeId,
                    'role' => $role,
                    'deadline_date' => $airDate->copy()->subDays(\App\Constants\WorkflowStep::getDeadlineDaysForRole($role, $category))->format('Y-m-d H:i:s'),
                    'description' => 'Deadline for ' . $role,
                    'auto_generated' => true,
                    'created_by' => $currentUserId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // BULK INSERT
        if (!empty($deadlinesToInsert)) {
            $chunks = array_chunk($deadlinesToInsert, 500);
            foreach ($chunks as $chunk) {
                \DB::table('deadlines')->insert($chunk);
            }
        }

        // Note: Deadline notifications will be sent separately if needed
        // to avoid N+1 in notification creation as well
    }

    /**
     * Generate 53 episodes untuk tahun tertentu
     * Episode number RESET ke 1 setiap tahun baru (seperti jatah cuti)
     * @param int $year Tahun yang akan di-generate
     * @param int|null $targetDayOfWeek Hari tayang (0=Minggu, 6=Sabtu). Jika null, ambil dari start_date.
     * @return array Info tentang episode yang di-generate
     */
    public function generateEpisodesForYear(int $year, ?int $targetDayOfWeek = null): array
    {
        // Jika targetDayOfWeek null, ambil dari start_date program
        if ($targetDayOfWeek === null) {
            $targetDayOfWeek = Carbon::parse($this->start_date)->dayOfWeek;
        }

        // Cek apakah episode untuk tahun ini sudah ada
        $yearStart = Carbon::createFromDate($year, 1, 1, 'UTC')->setTime(0, 0, 0);
        $yearEnd = Carbon::createFromDate($year, 12, 31, 'UTC')->setTime(23, 59, 59);

        $existingEpisodes = $this->episodes()
            ->whereBetween('air_date', [$yearStart, $yearEnd])
            ->whereNull('deleted_at')
            ->count();

        if ($existingEpisodes > 0) {
            return [
                'success' => false,
                'message' => "Episodes untuk tahun {$year} sudah ada ({$existingEpisodes} episode)",
                'year' => $year,
                'existing_count' => $existingEpisodes
            ];
        }

        // Cari hari pertama di bulan Januari tahun tersebut yang sesuai targetDayOfWeek
        $firstOccurrence = Carbon::createFromDate($year, 1, 1, 'UTC');
        $firstOccurrence->setTime(0, 0, 0);

        if ($firstOccurrence->dayOfWeek !== $targetDayOfWeek) {
            // Hitung berapa hari ke depan untuk sampai targetDayOfWeek
            $dayOfWeek = $firstOccurrence->dayOfWeek;
            $daysToAdd = ($targetDayOfWeek - $dayOfWeek + 7) % 7;
            if ($daysToAdd === 0)
                $daysToAdd = 7;
            $firstOccurrence->addDays($daysToAdd);
        }

        // Pastikan timezone UTC dan time 00:00:00
        $firstOccurrence->setTime(0, 0, 0)->utc();

        // Episode number RESET ke 1 setiap tahun (seperti jatah cuti)
        $startEpisodeNumber = 1;

        $generatedEpisodes = [];
        $currentUserId = auth()->id() ?? 1;
        $deadlinesToInsert = [];

        // Siapkan air_time
        $airTimeSource = $this->air_time;
        if ($airTimeSource instanceof \Carbon\Carbon) {
            $hour = $airTimeSource->hour;
            $minute = $airTimeSource->minute;
        } else {
            $timeParts = explode(':', (string)($airTimeSource ?? '00:00'));
            $hour = isset($timeParts[0]) ? (int)$timeParts[0] : 0;
            $minute = isset($timeParts[1]) ? (int)$timeParts[1] : 0;
        }

        // Generate 53 episode untuk tahun tersebut (Episode 1-53)
        for ($i = 0; $i < 53; $i++) {
            $episodeNumber = $startEpisodeNumber + $i; // Episode 1, 2, 3, ..., 52

            // Hitung air_date: Episode pertama = hari pertama ditemukan, berikutnya setiap 7 hari
            $airDate = $firstOccurrence->copy();
            if ($i > 0) {
                $airDate->addWeeks($i);
            }
            $airDate->setTime($hour, $minute, 0);

            // Production date = 7 hari sebelum tayang
            $productionDate = $airDate->copy()->subDays(7);
            $productionDate->utc()->setTime(0, 0, 0);

            // Format untuk insert
            $airDateStr = $airDate->format('Y-m-d H:i:s');
            $productionDateStr = $productionDate->format('Y-m-d H:i:s');

            // Insert episode
            $episodeId = \DB::table('episodes')->insertGetId([
                'program_id' => $this->id,
                'episode_number' => $episodeNumber,
                'title' => "Episode {$episodeNumber}",
                'description' => "Episode {$episodeNumber} dari program {$this->name} (Tahun {$year})",
                'air_date' => \DB::raw("'{$airDateStr}'"),
                'production_date' => \DB::raw("'{$productionDateStr}'"),
                'production_deadline' => \DB::raw("'{$productionDateStr}'"),
                'status' => 'draft',
                'current_workflow_state' => 'episode_generated',
                'format_type' => 'weekly',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // BULK DEADLINES using WorkflowStep constants
            $category = $this->category ?? 'regular';
            $isMusik = strtolower($category) === 'musik';
            $rolesList = $isMusik ? \App\Constants\WorkflowStep::MUSIC_ROLE_DEADLINE_DAYS : \App\Constants\WorkflowStep::ROLE_DEADLINE_DAYS;

            foreach (array_keys($rolesList) as $role) {
                $deadlinesToInsert[] = [
                    'episode_id' => $episodeId,
                    'role' => $role,
                    'deadline_date' => $airDate->copy()->subDays(\App\Constants\WorkflowStep::getDeadlineDaysForRole($role, $category))->format('Y-m-d H:i:s'),
                    'description' => 'Deadline for ' . $role,
                    'auto_generated' => true,
                    'created_by' => $currentUserId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            $generatedEpisodes[] = [
                'episode_number' => $episodeNumber,
                'air_date' => $airDateStr
            ];
        }

        // Insert All Deadlines
        if (!empty($deadlinesToInsert)) {
            $chunks = array_chunk($deadlinesToInsert, 500);
            foreach ($chunks as $chunk) {
                \DB::table('deadlines')->insert($chunk);
            }
        }

        return [
            'success' => true,
            'message' => "Berhasil generate 53 episode untuk program '{$this->name}'",
            'generated_count' => count($generatedEpisodes)
        ];
    }

    /**
     * Generate episode untuk tahun berikutnya (auto-detect)
     */
    public function generateNextYearEpisodes(): array
    {
        // Cari episode terakhir untuk detect tahun berikutnya
        $lastEpisode = $this->episodes()
            ->whereNull('deleted_at')
            ->orderBy('air_date', 'desc')
            ->first();

        $year = now()->year;
        $dayOfWeek = Carbon::parse($this->start_date)->dayOfWeek;

        if ($lastEpisode) {
            $lastAirDate = Carbon::parse($lastEpisode->air_date);
            $year = $lastAirDate->year + 1;
            $dayOfWeek = $lastAirDate->dayOfWeek;
        }

        return $this->generateEpisodesForYear($year, $dayOfWeek);
    }

    /**
     * Check apakah perlu generate episode untuk tahun berikutnya
     * @return array Info tentang status dan tahun berikutnya
     */
    public function checkNextYearEpisodes(): array
    {
        $lastEpisode = $this->episodes()
            ->whereNull('deleted_at')
            ->orderBy('air_date', 'desc')
            ->first();

        if (!$lastEpisode) {
            return [
                'needs_generation' => false,
                'message' => 'Belum ada episode untuk program ini',
                'next_year' => null
            ];
        }

        $lastAirDate = Carbon::parse($lastEpisode->air_date);
        $nextYear = $lastAirDate->year + 1;
        $currentYear = Carbon::now()->year;

        // Cek apakah tahun berikutnya sudah ada episode
        $yearStart = Carbon::createFromDate($nextYear, 1, 1, 'UTC')->setTime(0, 0, 0);
        $yearEnd = Carbon::createFromDate($nextYear, 12, 31, 'UTC')->setTime(23, 59, 59);

        $existingEpisodes = $this->episodes()
            ->whereBetween('air_date', [$yearStart, $yearEnd])
            ->whereNull('deleted_at')
            ->count();

        // Cari hari pertama di tahun berikutnya yang sesuai dengan hari tayang program
        $targetDayOfWeek = Carbon::parse($this->start_date)->dayOfWeek;
        $firstOccurrence = Carbon::createFromDate($nextYear, 1, 1)->setTime(0, 0, 0);

        if ($firstOccurrence->dayOfWeek !== $targetDayOfWeek) {
            $dayOfWeek = $firstOccurrence->dayOfWeek;
            $daysToAdd = ($targetDayOfWeek - $dayOfWeek + 7) % 7;
            if ($daysToAdd === 0)
                $daysToAdd = 7;
            $firstOccurrence->addDays($daysToAdd);
        }

        return [
            'needs_generation' => $existingEpisodes === 0 && $nextYear <= $currentYear + 1,
            'next_year' => $nextYear,
            'first_occurrence' => $firstOccurrence->format('Y-m-d'),
            'last_episode_date' => $lastAirDate->format('Y-m-d'),
            'last_episode_number' => $lastEpisode->episode_number,
            'existing_episodes_next_year' => $existingEpisodes,
            'message' => $existingEpisodes > 0
                ? "Episode untuk tahun {$nextYear} sudah ada ({$existingEpisodes} episode)"
                : ($nextYear > $currentYear + 1
                    ? "Tahun {$nextYear} masih terlalu jauh, generate saat mendekati tahun tersebut"
                    : "Perlu generate episode untuk tahun {$nextYear} (Episode akan reset ke 1-52)")
        ];
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        $totalEpisodes = $this->episodes()->count();
        if ($totalEpisodes === 0)
            return 0;

        $completedEpisodes = $this->episodes()
            ->where(function($q) {
                $q->where('status', 'aired')
                  ->orWhereIn('current_workflow_state', ['broadcasting', 'program_manager', 'program_dikredit']);
            })
            ->count();
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
     * Scope untuk program live TV
     */
    public function scopeLiveTv($query)
    {
        return $query->where('category', 'live_tv');
    }
}