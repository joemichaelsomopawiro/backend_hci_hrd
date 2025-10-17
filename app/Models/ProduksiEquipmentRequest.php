<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduksiEquipmentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'equipment_list',
        'request_notes',
        'status',
        'approved_by',
        'approved_at',
        'shooting_date',
        'return_date',
        'approval_notes',
        'rejection_notes',
        'created_by'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'approved_at' => 'datetime',
        'shooting_date' => 'date',
        'return_date' => 'date'
    ];

    /**
     * Relasi dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class);
    }

    /**
     * Relasi dengan User (Created By)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * Check if equipment can be used
     */
    public function canBeUsed(): bool
    {
        return $this->status === 'approved' && $this->shooting_date;
    }

    /**
     * Check if equipment can be returned
     */
    public function canBeReturned(): bool
    {
        return $this->status === 'in_use';
    }

    /**
     * Approve equipment request
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
     * Mark equipment as in use
     */
    public function markAsInUse($shootingDate): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        $this->update([
            'status' => 'in_use',
            'shooting_date' => $shootingDate
        ]);

        return true;
    }

    /**
     * Return equipment
     */
    public function returnEquipment($returnDate): bool
    {
        if (!$this->canBeReturned()) {
            return false;
        }

        $this->update([
            'status' => 'returned',
            'return_date' => $returnDate
        ]);

        return true;
    }

    /**
     * Reject equipment request
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
