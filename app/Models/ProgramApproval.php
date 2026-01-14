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
        'approvable_type',
        'approvable_id',
        'approval_type',
        'requested_by',
        'request_notes',
        'request_data',
        'approved_by',
        'approved_at',
        'approval_notes',
        'status',
        'priority',
        'requested_at'
    ];

    protected $casts = [
        'request_data' => 'array',
        'approved_at' => 'datetime',
        'requested_at' => 'datetime'
    ];

    /**
     * Polymorphic relationship - entity yang perlu approval
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User yang request approval
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * User yang approve
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('approval_type', $type);
    }
}
