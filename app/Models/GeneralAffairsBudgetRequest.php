<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralAffairsBudgetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'requested_amount',
        'purpose',
        'status',
        'approved_by',
        'approved_at',
        'released_at',
        'approval_notes',
        'rejection_notes'
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'released_at' => 'datetime'
    ];

    /**
     * Relasi dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class);
    }

    /**
     * Relasi dengan User (Approved By)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if request can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request can be released
     */
    public function canBeReleased(): bool
    {
        return $this->status === 'approved' && !$this->released_at;
    }

    /**
     * Approve budget request
     */
    public function approve($userId, $notes = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);

        return true;
    }

    /**
     * Release funds
     */
    public function releaseFunds(): bool
    {
        if (!$this->canBeReleased()) {
            return false;
        }

        $this->update([
            'status' => 'released',
            'released_at' => now()
        ]);

        return true;
    }

    /**
     * Reject budget request
     */
    public function reject($notes = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'rejection_notes' => $notes
        ]);

        return true;
    }
}
