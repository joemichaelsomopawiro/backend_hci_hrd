<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'script_content',
        'storyboard_data',
        'budget_data',
        'recording_schedule',
        'shooting_schedule',
        'shooting_location',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason'
    ];

    protected $casts = [
        'storyboard_data' => 'array',
        'budget_data' => 'array',
        'recording_schedule' => 'datetime',
        'shooting_schedule' => 'datetime',
        'reviewed_at' => 'datetime'
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
     * Submit creative work for review
     */
    public function submitForReview(): void
    {
        $this->update(['status' => 'submitted']);
    }

    /**
     * Approve creative work
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
     * Reject creative work
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Get total budget amount
     */
    public function getTotalBudgetAttribute(): float
    {
        if (!$this->budget_data) return 0;
        
        $total = 0;
        foreach ($this->budget_data as $item) {
            if (isset($item['amount'])) {
                $total += (float) $item['amount'];
            }
        }
        
        return $total;
    }

    /**
     * Get formatted budget data
     */
    public function getFormattedBudgetDataAttribute(): array
    {
        if (!$this->budget_data) return [];
        
        $formatted = [];
        foreach ($this->budget_data as $item) {
            $formatted[] = [
                'category' => $item['category'] ?? 'Unknown',
                'description' => $item['description'] ?? '',
                'amount' => number_format($item['amount'] ?? 0, 0, ',', '.'),
                'currency' => $item['currency'] ?? 'IDR'
            ];
        }
        
        return $formatted;
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk creative work yang submitted
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope untuk creative work yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk creative work yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}