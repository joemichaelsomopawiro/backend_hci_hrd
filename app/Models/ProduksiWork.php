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
        'notes',
        'completed_at',
        'completed_by'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'equipment_requests' => 'array',
        'needs_list' => 'array',
        'needs_requests' => 'array',
        'shooting_files' => 'array',
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
     * Relationship dengan User yang complete
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
     * Complete work
     */
    public function completeWork(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
            'notes' => $notes
        ]);
    }

    /**
     * Check if work can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress';
    }
}

