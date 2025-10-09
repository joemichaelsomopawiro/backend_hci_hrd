<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProgramApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_id',
        'approvable_type',
        'approval_type',
        'requested_by',
        'requested_at',
        'request_notes',
        'request_data',
        'current_data',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_notes',
        'priority',
        'due_date'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'due_date' => 'datetime',
        'request_data' => 'array',
        'current_data' => 'array'
    ];

    const APPROVAL_TYPES = [
        'program_proposal' => 'Approval Proposal Program',
        'program_schedule' => 'Approval Jadwal Tayang Program',
        'episode_rundown' => 'Approval Rundown Episode',
        'production_schedule' => 'Approval Jadwal Syuting',
        'schedule_change' => 'Approval Perubahan Jadwal',
        'schedule_cancellation' => 'Approval Pembatalan Jadwal',
        'deadline_extension' => 'Approval Perpanjangan Deadline'
    ];

    const PRIORITIES = [
        'low' => 'Rendah',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak'
    ];

    /**
     * Polymorphic relationship
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relasi dengan User (Requested By)
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relasi dengan User (Reviewed By)
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Relasi dengan User (Approved By)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi dengan User (Rejected By)
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get approval type label
     */
    public function getApprovalTypeLabelAttribute(): string
    {
        return self::APPROVAL_TYPES[$this->approval_type] ?? $this->approval_type;
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Mark as reviewed
     */
    public function markAsReviewed(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Approve
     */
    public function approve(int $approverId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    /**
     * Reject
     */
    public function reject(int $rejecterId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejecterId,
            'rejected_at' => now(),
            'rejection_notes' => $notes
        ]);
    }

    /**
     * Cancel (by requester)
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled'
        ]);
    }

    /**
     * Check if approval is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date < now() && 
               !in_array($this->status, ['approved', 'rejected', 'cancelled']);
    }

    /**
     * Check if approval is urgent
     */
    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    /**
     * Scope: Pending approvals
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: By approval type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('approval_type', $type);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Overdue approvals
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'cancelled']);
    }

    /**
     * Scope: Urgent approvals
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent')
            ->whereNotIn('status', ['approved', 'rejected', 'cancelled']);
    }
}

