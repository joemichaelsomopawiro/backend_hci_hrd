<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduksiWork extends Model
{
    use HasFactory;

    protected $table = 'produksi_works';

    protected $fillable = [
        'episode_id',
        'creative_work_id',
        'run_sheet_id',
        'created_by',
        'status',
        'equipment_list',
        'equipment_requests',
        'needs_list',
        'needs_requests',
        'shooting_files',
        'shooting_file_links',
        'producer_requests',
        'notes',
        'setting_completed_at',
        'setting_completed_by',
        'completed_at',
        'completed_by',
        'crew_attendances'
    ];

    protected $appends = ['episode_display'];

    protected $casts = [
        'equipment_list' => 'array',
        'equipment_requests' => 'array',
        'needs_list' => 'array',
        'needs_requests' => 'array',
        'shooting_files' => 'array',
        'shooting_file_links' => 'array',
        'producer_requests' => 'array',
        'crew_attendances' => 'array',
        'setting_completed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan Creative Work
     */
    public function creativeWork(): BelongsTo
    {
        return $this->belongsTo(CreativeWork::class);
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang complete (Tim Syuting — selesai akhir)
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Relationship dengan User yang selesai bagian Tim Setting
     */
    public function settingCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'setting_completed_by');
    }

    /**
     * Relationship dengan Production Equipment Requests
     */
    public function equipmentRequests(): HasMany
    {
        return $this->hasMany(ProductionEquipment::class, 'episode_id', 'episode_id');
    }

    /**
     * Relationship dengan Shooting Run Sheet
     */
    public function runSheet(): BelongsTo
    {
        return $this->belongsTo(ShootingRunSheet::class, 'run_sheet_id');
    }

    /**
     * Accept work
     */
    public function acceptWork(int $userId): void
    {
        $this->update([
            'status' => 'in_progress',
            'created_by' => $userId
        ]);
    }

    /**
     * Selesai bagian Tim Setting saja (work tetap in_progress untuk Tim Syuting)
     */
    public function completeSettingPart(int $userId, ?string $notes = null): void
    {
        $this->update([
            'setting_completed_at' => now(),
            'setting_completed_by' => $userId,
            'notes' => $notes ?: $this->notes
        ]);
    }

    /**
     * Complete work (akhir — dipanggil Tim Syuting setelah run sheet, link file, kembalikan alat)
     */
    public function completeWork(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
            'notes' => $notes ?: $this->notes
        ]);
    }

    /**
     * Check if work can be completed (Tim Syuting: run sheet + shooting files wajib)
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress';
    }

    /** Apakah bagian Tim Setting sudah ditandai selesai */
    public function isSettingCompleted(): bool
    {
        return $this->setting_completed_at !== null;
    }

    /**
     * Display text for episode (e.g. "Ep. 3") so frontend does not show work id or episode id.
     */
    public function getEpisodeDisplayAttribute(): ?string
    {
        if (!$this->relationLoaded('episode') || !$this->episode) {
            return null;
        }
        $num = $this->episode->episode_number ?? null;
        return $num !== null && $num !== '' ? 'Ep. ' . $num : null;
    }
}

