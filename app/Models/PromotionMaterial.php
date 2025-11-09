<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'material_type',
        'material_notes',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'platform',
        'platform_notes',
        'social_media_links',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'qc_feedback'
    ];

    protected $casts = [
        'social_media_links' => 'array',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer'
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
     * Relationship dengan User yang review
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Submit material for review
     */
    public function submitForReview(): void
    {
        $this->update(['status' => 'submitted']);
    }

    /**
     * Approve material
     */
    public function approve(int $reviewedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Reject material
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $reason
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
     * Get material type label
     */
    public function getMaterialTypeLabelAttribute(): string
    {
        $labels = [
            'bts_video' => 'BTS Video',
            'bts_photo' => 'BTS Photo',
            'highlight_ig' => 'Highlight Instagram',
            'highlight_facebook' => 'Highlight Facebook',
            'highlight_tv' => 'Highlight TV',
            'advertisement' => 'Advertisement'
        ];

        return $labels[$this->material_type] ?? $this->material_type;
    }

    /**
     * Scope berdasarkan material type
     */
    public function scopeByMaterialType($query, $type)
    {
        return $query->where('material_type', $type);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope berdasarkan platform
     */
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope untuk material yang draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope untuk material yang creating
     */
    public function scopeCreating($query)
    {
        return $query->where('status', 'creating');
    }

    /**
     * Scope untuk material yang completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope untuk material yang reviewed
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope untuk material yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
