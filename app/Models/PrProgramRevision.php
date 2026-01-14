<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrProgramRevision extends Model
{
    use HasFactory;

    protected $table = 'pr_program_revisions';

    protected $fillable = [
        'program_id',
        'revision_type',
        'before_data',
        'after_data',
        'revision_reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes'
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'reviewed_at' => 'datetime'
    ];

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PrProgram::class, 'program_id');
    }

    /**
     * Relationship dengan User yang request revisi
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship dengan User yang review revisi
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if revision is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if revision is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
