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
        'production_notes',
        
        // Creative fields
        'script_submitted_at', 'script_submitted_by',
        
        // Producer review fields
        'rundown_approved_at', 'rundown_approved_by', 'rundown_rejected_at',
        'rundown_rejected_by', 'rundown_rejection_notes', 'rundown_revision_points', 'producer_notes',
        
        // Produksi fields
        'raw_file_urls', 'shooting_notes', 'actual_shooting_date',
        'shooting_completed_at', 'shooting_completed_by', 'budget_talent',
        
        // Editor fields
        'editing_status', 'editing_started_at', 'editing_started_by', 'editing_notes',
        'editing_drafts', 'final_file_url', 'editing_completion_notes',
        'edited_duration_minutes', 'final_file_size_mb', 'editing_completed_at',
        'editing_completed_by', 'editing_revisions', 'revision_acknowledged_at',
        'revision_acknowledged_by',
        
        // QC fields
        'qc_approved_at', 'qc_approved_by', 'qc_revision_requested_at',
        'qc_revision_requested_by', 'qc_revision_count',
        
        // Broadcasting fields
        'seo_title', 'seo_description', 'seo_tags', 'youtube_category',
        'youtube_privacy', 'metadata_updated_at', 'metadata_updated_by',
        'youtube_url', 'youtube_video_id', 'youtube_upload_status',
        'youtube_upload_started_at', 'youtube_uploaded_at', 'youtube_upload_by',
        'website_url', 'website_published_at', 'website_published_by',
        'broadcast_notes', 'actual_air_date', 'broadcast_completed_at', 'broadcast_completed_by',
        
        // Design Grafis fields
        'thumbnail_youtube', 'thumbnail_bts', 'design_assets_talent_photos',
        'design_assets_bts_photos', 'design_assets_production_files',
        'design_assets_received_at', 'design_assets_received_by', 'design_assets_notes',
        'thumbnail_youtube_uploaded_at', 'thumbnail_youtube_uploaded_by', 'thumbnail_youtube_notes',
        'thumbnail_bts_uploaded_at', 'thumbnail_bts_uploaded_by', 'thumbnail_bts_notes',
        'design_completed_at', 'design_completed_by', 'design_completion_notes',
        
        // Promosi fields
        'promosi_bts_video_urls', 'promosi_talent_photo_urls', 'promosi_bts_notes',
        'promosi_bts_completed_at', 'promosi_bts_completed_by',
        'promosi_ig_story_urls', 'promosi_fb_reel_urls', 'promosi_highlight_notes',
        'promosi_highlight_completed_at', 'promosi_highlight_completed_by',
        'promosi_social_shares'
    ];

    protected $casts = [
        'air_date' => 'datetime',
        'production_date' => 'date',
        'talent_data' => 'array',
        'production_notes' => 'array',
        'rundown_revision_points' => 'array',
        'raw_file_urls' => 'array',
        'editing_drafts' => 'array',
        'editing_revisions' => 'array',
        'seo_tags' => 'array',
        'design_assets_talent_photos' => 'array',
        'design_assets_bts_photos' => 'array',
        'design_assets_production_files' => 'array',
        'promosi_bts_video_urls' => 'array',
        'promosi_talent_photo_urls' => 'array',
        'promosi_ig_story_urls' => 'array',
        'promosi_fb_reel_urls' => 'array',
        'promosi_social_shares' => 'array',
        'script_submitted_at' => 'datetime',
        'rundown_approved_at' => 'datetime',
        'rundown_rejected_at' => 'datetime',
        'shooting_completed_at' => 'datetime',
        'editing_started_at' => 'datetime',
        'editing_completed_at' => 'datetime',
        'qc_approved_at' => 'datetime',
        'qc_revision_requested_at' => 'datetime',
        'metadata_updated_at' => 'datetime',
        'youtube_upload_started_at' => 'datetime',
        'youtube_uploaded_at' => 'datetime',
        'website_published_at' => 'datetime',
        'actual_air_date' => 'datetime',
        'broadcast_completed_at' => 'datetime',
        'design_assets_received_at' => 'datetime',
        'thumbnail_youtube_uploaded_at' => 'datetime',
        'thumbnail_bts_uploaded_at' => 'datetime',
        'design_completed_at' => 'datetime',
        'promosi_bts_completed_at' => 'datetime',
        'promosi_highlight_completed_at' => 'datetime'
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
     * Relasi dengan QC (Quality Control)
     */
    public function qc()
    {
        return $this->hasOne(EpisodeQC::class, 'program_episode_id')->latest();
    }

    /**
     * Relasi dengan QC History
     */
    public function qcHistory(): HasMany
    {
        return $this->hasMany(EpisodeQC::class, 'program_episode_id');
    }

    /**
     * Relasi dengan User - Script Submitted By
     */
    public function scriptSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'script_submitted_by');
    }

    /**
     * Relasi dengan User - Rundown Approved By
     */
    public function rundownApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rundown_approved_by');
    }

    /**
     * Relasi dengan User - Shooting Completed By
     */
    public function shootingCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shooting_completed_by');
    }

    /**
     * Relasi dengan User - Editing Completed By
     */
    public function editingCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editing_completed_by');
    }

    /**
     * Pseudo-relationship untuk editorWork (data dari episode fields)
     * Untuk backward compatibility dengan controller yang expect editorWork relationship
     */
    public function getEditorWorkAttribute()
    {
        return (object)[
            'status' => $this->editing_status,
            'final_file_url' => $this->final_file_url,
            'completed_at' => $this->editing_completed_at,
            'completion_notes' => $this->editing_completion_notes
        ];
    }

    /**
     * Pseudo-relationship untuk designGrafisWork
     */
    public function getDesignGrafisWorkAttribute()
    {
        return (object)[
            'status' => $this->design_completed_at ? 'completed' : 'pending',
            'completed_at' => $this->design_completed_at,
            'thumbnails' => function() {
                return (object)[
                    'youtube' => $this->thumbnail_youtube,
                    'bts' => $this->thumbnail_bts
                ];
            }
        ];
    }

    /**
     * Pseudo-relationship untuk promosi
     */
    public function getPromosiAttribute()
    {
        return (object)[
            'talent_photos' => $this->promosi_talent_photo_urls,
            'bts_photos' => $this->promosi_bts_video_urls
        ];
    }

    /**
     * Pseudo-relationship untuk produksi
     */
    public function getProduksiAttribute()
    {
        return (object)[
            'raw_files' => $this->raw_file_urls,
            'shooting_notes' => $this->shooting_notes
        ];
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

