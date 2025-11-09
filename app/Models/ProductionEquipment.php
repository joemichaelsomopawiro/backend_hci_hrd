<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEquipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'equipment_list',
        'request_notes',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_notes',
        'rejection_reason',
        'assigned_at',
        'returned_at',
        'assigned_to'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'assigned_at' => 'datetime',
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
     * Relationship dengan User yang assigned
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Approve equipment request
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
     * Reject equipment request
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
     * Assign equipment
     */
    public function assign(int $assignedTo): void
    {
        $this->update([
            'status' => 'in_use',
            'assigned_to' => $assignedTo,
            'assigned_at' => now()
        ]);
    }

    /**
     * Return equipment
     */
    public function return(): void
    {
        $this->update([
            'status' => 'returned',
            'returned_at' => now()
        ]);
    }

    /**
     * Get equipment list as formatted string
     */
    public function getFormattedEquipmentListAttribute(): string
    {
        if (!$this->equipment_list) return 'N/A';
        
        return implode(', ', $this->equipment_list);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
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
     * Scope untuk request yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope untuk equipment yang in use
     */
    public function scopeInUse($query)
    {
        return $query->where('status', 'in_use');
    }

    /**
     * Scope untuk equipment yang returned
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }
}