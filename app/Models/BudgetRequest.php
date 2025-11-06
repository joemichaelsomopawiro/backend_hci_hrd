<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id',
        'requested_by',
        'request_type',
        'title',
        'description',
        'requested_amount',
        'approved_amount',
        'status',
        'approval_notes',
        'rejection_reason',
        'rejection_notes',
        'payment_method',
        'payment_schedule',
        'payment_date',
        'payment_receipt',
        'payment_notes',
        'approved_by',
        'rejected_by',
        'processed_by',
        'approved_at',
        'rejected_at'
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'payment_schedule' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    /**
     * Relationship: Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Relationship: Requested By
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship: Approved By
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Rejected By
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Relationship: Processed By
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}









