<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'music_submission_id',
        'requested_by',
        'requested_amount',
        'request_reason',
        'approved_by',
        'approved_amount',
        'approval_notes',
        'status',
        'requested_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function musicSubmission()
    {
        return $this->belongsTo(MusicSubmission::class, 'music_submission_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
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
        return $query->whereIn('status', ['approved', 'revised']);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Helpers
     */
    public function isApproved()
    {
        return in_array($this->status, ['approved', 'revised']);
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isRevised()
    {
        return $this->status === 'revised' && $this->approved_amount != $this->requested_amount;
    }

    public function formatCurrency($amount)
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    public function getRequestedAmountFormatted()
    {
        return $this->formatCurrency($this->requested_amount);
    }

    public function getApprovedAmountFormatted()
    {
        return $this->approved_amount ? $this->formatCurrency($this->approved_amount) : '-';
    }

    public function getDifferenceAmount()
    {
        if ($this->approved_amount && $this->requested_amount) {
            return $this->approved_amount - $this->requested_amount;
        }
        return 0;
    }

    public function getDifferenceAmountFormatted()
    {
        $diff = $this->getDifferenceAmount();
        $sign = $diff >= 0 ? '+' : '';
        return $sign . $this->formatCurrency(abs($diff));
    }

    public function getStatusLabel()
    {
        $labels = [
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revised' => 'Approved (Revised Amount)',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'revised' => 'blue',
        ];

        return $colors[$this->status] ?? 'gray';
    }
}






