<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'qc_type',
        'status',
        'qc_notes',
        'feedback',
        'qc_checklist',
        'quality_score',
        'improvement_areas',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'created_by',
        'qc_by',
        'qc_started_at',
        'qc_completed_at',
        'qc_result_notes',
        'screenshots'
    ];

    protected $casts = [
        'qc_checklist' => 'array',
        'improvement_areas' => 'array',
        'screenshots' => 'array',
        'qc_started_at' => 'datetime',
        'qc_completed_at' => 'datetime',
        'file_size' => 'integer',
        'quality_score' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang QC
     */
    public function qcBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_by');
    }

    /**
     * Start QC
     */
    public function startQC(int $qcBy): void
    {
        $this->update([
            'status' => 'in_progress',
            'qc_by' => $qcBy,
            'qc_started_at' => now()
        ]);
    }

    /**
     * Complete QC
     */
    public function completeQC(int $qualityScore, array $improvementAreas = [], ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'quality_score' => $qualityScore,
            'improvement_areas' => $improvementAreas,
            'qc_result_notes' => $notes,
            'qc_completed_at' => now()
        ]);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get QC type label
     */
    public function getQcTypeLabelAttribute(): string
    {
        $labels = [
            'video_bts' => 'Video BTS',
            'advertisement_tv' => 'Advertisement TV',
            'highlight_ig' => 'Highlight Instagram',
            'highlight_tv' => 'Highlight TV',
            'highlight_facebook' => 'Highlight Facebook',
            'thumbnail_yt' => 'Thumbnail YouTube',
            'thumbnail_bts' => 'Thumbnail BTS',
            'main_episode' => 'Main Episode'
        ];

        return $labels[$this->qc_type] ?? $this->qc_type;
    }

    /**
     * Scope berdasarkan QC type
     */
    public function scopeByQcType($query, $type)
    {
        return $query->where('qc_type', $type);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk QC yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk QC yang in progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope untuk QC yang completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope untuk QC yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk QC yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
