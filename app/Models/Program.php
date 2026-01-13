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
     * Generate 52 episodes untuk program (1 tahun = 52 minggu)
     * Episode 1 = Sabtu pertama di tahun program
     * Setiap episode tayang setiap Sabtu (mingguan)
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
        
        // Detect Sabtu pertama di tahun program (dari start_date)
        // Parse start_date dan ambil tahunnya (pastikan tanpa timezone issue)
        $startDate = Carbon::parse($this->start_date);
        $year = $startDate->year;
        
        // Cari Sabtu pertama di bulan Januari tahun tersebut
        // Gunakan createFromDate dengan timezone UTC dari awal untuk menghindari timezone shift
        $firstSaturday = Carbon::createFromDate($year, 1, 1, 'UTC');
        $firstSaturday->setTime(0, 0, 0);
        
        // Jika tanggal 1 bukan Sabtu, hitung hari ke depan untuk sampai Sabtu pertama
        if ($firstSaturday->dayOfWeek !== Carbon::SATURDAY) {
            // dayOfWeek: 0=Minggu, 1=Senin, ..., 6=Sabtu
            // Hitung berapa hari ke depan untuk sampai Sabtu (hari ke-6)
            $dayOfWeek = $firstSaturday->dayOfWeek;
            $daysToAdd = (6 - $dayOfWeek + 7) % 7;
            if ($daysToAdd === 0) $daysToAdd = 7; // Jika hari ini sudah Sabtu (tidak akan terjadi karena sudah di-check)
            $firstSaturday->addDays($daysToAdd);
        }
        
        // Pastikan timezone UTC dan time 00:00:00
        $firstSaturday->setTime(0, 0, 0)->utc();
        
        // Generate 52 episode (1 tahun = 52 minggu)
        for ($i = 1; $i <= 52; $i++) {
            // Episode 1 = Sabtu pertama, Episode 2 = Sabtu berikutnya (7 hari kemudian), dst
            // Copy dulu, baru add weeks untuk menghindari mutation
            $airDate = $firstSaturday->copy();
            if ($i > 1) {
                $airDate->addWeeks($i - 1);
            }
            // Pastikan timezone UTC dan time 00:00:00 untuk konsistensi
            $airDate->utc()->setTime(0, 0, 0);
            
            // Production date = 7 hari sebelum tayang
            $productionDate = $airDate->copy()->subDays(7);
            $productionDate->utc()->setTime(0, 0, 0);
            
            // Gunakan DB::table dengan DB::raw untuk insert langsung tanpa timezone conversion
            // Format: INSERT dengan timezone UTC eksplisit menggunakan DB::raw
            $airDateStr = $airDate->format('Y-m-d H:i:s');
            $productionDateStr = $productionDate->format('Y-m-d H:i:s');
            
            // Insert langsung dengan DB::table dan DB::raw untuk menghindari timezone conversion
            $episodeId = \DB::table('episodes')->insertGetId([
                'program_id' => $this->id,
                'episode_number' => $i,
                'title' => "Episode {$i}",
                'description' => "Episode {$i} dari program {$this->name}",
                'air_date' => \DB::raw("'{$airDateStr}'"),  // Raw string tanpa timezone conversion
                'production_date' => \DB::raw("'{$productionDateStr}'"),
                'status' => 'draft',
                'current_workflow_state' => 'program_created',
                'format_type' => 'weekly',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Load episode untuk generate deadlines
            $episode = \App\Models\Episode::find($episodeId);

            // Generate deadlines untuk episode
            // Deadline Editor: 7 hari sebelum tayang
            // Deadline Creative & Produksi: 9 hari sebelum tayang
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