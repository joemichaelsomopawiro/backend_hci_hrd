<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EquipmentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'requested_by',
        'reviewed_by',
        'equipment_list',
        'requested_date',
        'needed_date',
        'return_date',
        'purpose',
        'notes',
        'status',
        'review_notes',
        'approved_equipment',
        'rejected_equipment',
        'reviewed_at',
        'picked_up_at',
        'returned_at'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'approved_equipment' => 'array',
        'rejected_equipment' => 'array',
        'requested_date' => 'date',
        'needed_date' => 'date',
        'return_date' => 'date',
        'reviewed_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'returned_at' => 'datetime'
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
     * Relationship dengan User yang review
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope untuk request yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk request yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk request yang overdue
     */
    public function scopeOverdue($query)
    {
        return $query->where('return_date', '<', now())
                    ->where('status', 'in_use');
    }

    /**
     * Check if request is overdue
     */
    public function isOverdue(): bool
    {
        return $this->return_date < now() && $this->status === 'in_use';
    }

    /**
     * Get days until return
     */
    public function getDaysUntilReturnAttribute(): int
    {
        return now()->diffInDays($this->return_date, false);
    }

    /**
     * Mark as picked up
     */
    public function markAsPickedUp(): void
    {
        $this->update([
            'status' => 'in_use',
            'picked_up_at' => now()
        ]);
    }

    /**
     * Mark as returned
     */
    public function markAsReturned(): void
    {
        $this->update([
            'status' => 'returned',
            'returned_at' => now()
        ]);
    }
}













