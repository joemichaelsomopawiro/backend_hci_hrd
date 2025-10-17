<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_submission_id',
        'created_by',
        'talent_budget',
        'production_budget',
        'other_budget',
        'total_budget',
        'budget_notes',
        'talent_budget_notes',
        'production_budget_notes',
        'other_budget_notes',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'requires_special_approval',
        'standard_budget_limit',
    ];

    protected $casts = [
        'talent_budget' => 'decimal:2',
        'production_budget' => 'decimal:2',
        'other_budget' => 'decimal:2',
        'total_budget' => 'decimal:2',
        'standard_budget_limit' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'requires_special_approval' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function musicSubmission()
    {
        return $this->belongsTo(MusicSubmission::class, 'music_submission_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvals()
    {
        return $this->hasMany(BudgetApproval::class);
    }

    public function latestApproval()
    {
        return $this->hasOne(BudgetApproval::class)->latestOfMany();
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'under_review']);
    }

    public function scopeRequiresSpecialApproval($query)
    {
        return $query->where('requires_special_approval', true)
                     ->where('status', 'pending_special_approval');
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'special_approved']);
    }

    /**
     * Helpers
     */
    public function calculateTotal()
    {
        $this->total_budget = $this->talent_budget + $this->production_budget + $this->other_budget;
        return $this->total_budget;
    }

    public function checkIfRequiresSpecialApproval($limit = null)
    {
        $limit = $limit ?? $this->standard_budget_limit ?? 10000000; // Default 10 juta
        $this->requires_special_approval = $this->total_budget > $limit;
        return $this->requires_special_approval;
    }

    public function isApproved()
    {
        return in_array($this->status, ['approved', 'special_approved']);
    }

    public function isPending()
    {
        return in_array($this->status, ['draft', 'submitted', 'under_review', 'pending_special_approval']);
    }

    public function formatCurrency($amount)
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    public function getTalentBudgetFormatted()
    {
        return $this->formatCurrency($this->talent_budget);
    }

    public function getProductionBudgetFormatted()
    {
        return $this->formatCurrency($this->production_budget);
    }

    public function getOtherBudgetFormatted()
    {
        return $this->formatCurrency($this->other_budget);
    }

    public function getTotalBudgetFormatted()
    {
        return $this->formatCurrency($this->total_budget);
    }

    public function getStatusLabel()
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'pending_special_approval' => 'Pending Special Approval',
            'special_approved' => 'Special Approved',
            'rejected' => 'Rejected',
            'revision' => 'Needs Revision',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'draft' => 'gray',
            'submitted' => 'blue',
            'under_review' => 'yellow',
            'approved' => 'green',
            'pending_special_approval' => 'purple',
            'special_approved' => 'green',
            'rejected' => 'red',
            'revision' => 'orange',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Auto-calculate total on save
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($budget) {
            $budget->calculateTotal();
        });
    }
}







