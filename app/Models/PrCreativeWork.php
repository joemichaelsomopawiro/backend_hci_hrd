<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrCreativeWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'script_content',
        'storyboard_data',
        'budget_data',
        'recording_schedule',
        'shooting_schedule',
        'shooting_location',
        'script_approved',
        'script_review_notes',
        'script_approved_by',
        'script_approved_at',
        'storyboard_approved',
        'storyboard_review_notes',
        'storyboard_approved_by',
        'storyboard_approved_at',
        'budget_approved',
        'budget_review_notes',
        'budget_approved_by',
        'budget_approved_at',
        'requires_special_budget_approval',
        'special_budget_reason',
        'special_budget_approval_id',
        'special_budget_approved_at',
        'status',
        'created_by',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'storyboard_data' => 'array',
        'budget_data' => 'array',
        'recording_schedule' => 'datetime',
        'shooting_schedule' => 'datetime',
        'script_approved' => 'boolean',
        'storyboard_approved' => 'boolean',
        'budget_approved' => 'boolean',
        'requires_special_budget_approval' => 'boolean',
        'script_approved_at' => 'datetime',
        'storyboard_approved_at' => 'datetime',
        'budget_approved_at' => 'datetime',
        'special_budget_approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scriptApprovedBy()
    {
        return $this->belongsTo(User::class, 'script_approved_by');
    }

    public function storyboardApprovedBy()
    {
        return $this->belongsTo(User::class, 'storyboard_approved_by');
    }

    public function budgetApprovedBy()
    {
        return $this->belongsTo(User::class, 'budget_approved_by');
    }

    public function specialBudgetApprover()
    {
        return $this->belongsTo(User::class, 'special_budget_approval_id');
    }

    // Helper Methods
    public function isFullyApproved(): bool
    {
        $scriptOk = $this->script_approved === true;
        $storyboardOk = $this->storyboard_approved === true || $this->storyboard_data === null;
        $budgetOk = $this->budget_approved === true;

        // If requires special budget approval
        if ($this->requires_special_budget_approval) {
            return $scriptOk && $storyboardOk && $budgetOk && $this->special_budget_approval_id !== null;
        }

        return $scriptOk && $storyboardOk && $budgetOk;
    }
}
