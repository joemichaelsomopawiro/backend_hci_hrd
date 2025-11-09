<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'budget_type',
        'amount',
        'description',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_notes',
        'rejection_reason',
        'used_amount',
        'remaining_amount',
        'used_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'used_at' => 'datetime'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang request
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
     * Approve budget
     */
    public function approve(int $approvedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    /**
     * Reject budget
     */
    public function reject(int $rejectedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Use budget
     */
    public function useBudget(float $amount): void
    {
        $newUsedAmount = $this->used_amount + $amount;
        $newRemainingAmount = $this->amount - $newUsedAmount;
        
        $this->update([
            'used_amount' => $newUsedAmount,
            'remaining_amount' => $newRemainingAmount,
            'used_at' => now()
        ]);
    }

    /**
     * Get budget type label
     */
    public function getBudgetTypeLabelAttribute(): string
    {
        $labels = [
            'talent_fee' => 'Talent Fee',
            'equipment_rental' => 'Equipment Rental',
            'location_fee' => 'Location Fee',
            'transportation' => 'Transportation',
            'food_catering' => 'Food & Catering',
            'special_request' => 'Special Request'
        ];

        return $labels[$this->budget_type] ?? $this->budget_type;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Get formatted used amount
     */
    public function getFormattedUsedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->used_amount, 0, ',', '.');
    }

    /**
     * Get formatted remaining amount
     */
    public function getFormattedRemainingAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->remaining_amount, 0, ',', '.');
    }

    /**
     * Scope berdasarkan budget type
     */
    public function scopeByBudgetType($query, $type)
    {
        return $query->where('budget_type', $type);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk budget yang draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope untuk budget yang submitted
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope untuk budget yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk budget yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope untuk budget yang revised
     */
    public function scopeRevised($query)
    {
        return $query->where('status', 'revised');
    }
}