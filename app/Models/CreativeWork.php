<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\MusicArrangement;

class CreativeWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'script_content',
        'script_link',
        'storyboard_data',
        'storyboard_link',
        'budget_data',
        'recording_schedule',
        'shooting_schedule',
        'shooting_location',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        // Review fields
        'script_approved',
        'storyboard_approved',
        'budget_approved',
        'script_review_notes',
        'storyboard_review_notes',
        'budget_review_notes',
        // Special budget approval
        'requires_special_budget_approval',
        'special_budget_reason',
        'special_budget_approval_id',
        // Shooting schedule cancellation
        'shooting_schedule_cancelled',
        'shooting_cancellation_reason',
        'shooting_schedule_new'
    ];

    protected $casts = [
        'storyboard_data' => 'array',
        'budget_data' => 'array',
        'recording_schedule' => 'datetime',
        'shooting_schedule' => 'datetime',
        'shooting_schedule_new' => 'datetime',
        'reviewed_at' => 'datetime',
        'script_approved' => 'boolean',
        'storyboard_approved' => 'boolean',
        'budget_approved' => 'boolean',
        'requires_special_budget_approval' => 'boolean',
        'shooting_schedule_cancelled' => 'boolean'
    ];

    protected $appends = ['total_budget', 'formatted_budget_data', 'title', 'music_arrangement_id', 'music_arrangement', 'program_id', 'program_name', 'episode_number', 'approved_special_budget_amount', 'requested_special_budget_amount', 'is_special_budget_revised', 'special_budget_item', 'regular_budget'];

    /**
     * Accessor untuk ID Program
     */
    public function getProgramIdAttribute(): ?int
    {
        return $this->episode?->program_id;
    }

    /**
     * Accessor untuk Nama Program
     */
    public function getProgramNameAttribute(): string
    {
        return $this->episode?->program?->name ?? 'N/A';
    }

    /**
     * Accessor untuk Nomor Episode
     */
    public function getEpisodeNumberAttribute(): ?int
    {
        return $this->episode?->episode_number;
    }

    /**
     * Accessor untuk Title Episode
     */
    public function getTitleAttribute(): string
    {
        return $this->episode?->title ?? 'N/A';
    }

    /**
     * Relationship dengan Music Arrangement yang sudah di-approve (Latest)
     */
    public function latestApprovedMusicArrangement(): HasOne
    {
        return $this->hasOne(MusicArrangement::class, 'episode_id', 'episode_id')
            ->whereIn('status', ['arrangement_approved', 'approved'])
            ->latestOfMany('reviewed_at');
    }

    /**
     * Accessor untuk Music Arrangement (Approved)
     */
    public function getMusicArrangementAttribute(): ?MusicArrangement
    {
        return $this->latestApprovedMusicArrangement;
    }

    /**
     * Accessor untuk Music Arrangement ID
     */
    public function getMusicArrangementIdAttribute(): ?int
    {
        return $this->music_arrangement?->id;
    }

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship dengan User yang review
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Relationship dengan ProductionTeamAssignment (tim syuting, setting, recording)
     */
    public function teamAssignments()
    {
        return $this->hasMany(\App\Models\ProductionTeamAssignment::class, 'episode_id', 'episode_id');
    }

    /**
     * Relationship dengan ProgramApproval untuk special budget
     */
    public function specialBudgetApproval()
    {
        return $this->belongsTo(\App\Models\ProgramApproval::class, 'special_budget_approval_id');
    }

    /**
     * Get approved special budget amount (dari ProgramApproval yang sudah di-approve)
     */
    public function getApprovedSpecialBudgetAmountAttribute(): ?float
    {
        if (!$this->special_budget_approval_id) {
            return null;
        }
        
        $approval = $this->specialBudgetApproval;
        if (!$approval || $approval->status !== 'approved') {
            return null;
        }
        
        // Ambil dari request_data['approved_amount'] jika ada, atau dari request_data['special_budget_amount']
        $requestData = $approval->request_data ?? [];
        return $requestData['approved_amount'] ?? $requestData['special_budget_amount'] ?? null;
    }

    /**
     * Get requested special budget amount (dari ProgramApproval)
     */
    public function getRequestedSpecialBudgetAmountAttribute(): ?float
    {
        if (!$this->special_budget_approval_id) {
            return null;
        }
        
        $approval = $this->specialBudgetApproval;
        if (!$approval) {
            return null;
        }
        
        $requestData = $approval->request_data ?? [];
        return $requestData['special_budget_amount'] ?? null;
    }

    /**
     * Check if special budget was revised (approved_amount != requested_amount)
     */
    public function getIsSpecialBudgetRevisedAttribute(): bool
    {
        $approved = $this->approved_special_budget_amount;
        $requested = $this->requested_special_budget_amount;
        
        if ($approved === null || $requested === null) {
            return false;
        }
        
        return $approved != $requested;
    }

    /**
     * Submit creative work for review
     */
    public function submitForReview(): void
    {
        $this->update(['status' => 'submitted']);
    }

    /**
     * Approve creative work
     */
    public function approve(int $reviewedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Reject creative work
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Get total budget amount
     */
    public function getTotalBudgetAttribute(): float
    {
        if (!$this->budget_data) return 0;
        
        $total = 0;
        // Handle both Array of Objects [{amount: 100}] and Key-Value Object {talent_fee: 100}
        foreach ($this->budget_data as $key => $value) {
            if (is_array($value) && isset($value['amount'])) {
                $total += (float) $value['amount'];
            } elseif (is_numeric($value)) {
                $total += (float) $value;
            }
        }
        
        return $total;
    }

    /**
     * Get formatted budget data
     */
    public function getFormattedBudgetDataAttribute(): array
    {
        if (!$this->budget_data) return [];
        
        $formatted = [];
        // Handle both Array of Objects and Key-Value Object
        foreach ($this->budget_data as $key => $value) {
            if (is_array($value)) {
                $formatted[] = [
                    'category' => $value['category'] ?? $key,
                    'description' => $value['description'] ?? '',
                    'amount' => number_format($value['amount'] ?? 0, 0, ',', '.'),
                    'raw_amount' => $value['amount'] ?? 0, // Tambahkan raw_amount untuk frontend
                    'currency' => $value['currency'] ?? 'IDR',
                    'approved_by_manager' => $value['approved_by_manager'] ?? false,
                    'is_special_budget' => ($value['category'] ?? '') === 'Special Budget', // Flag untuk special budget
                    'approved_amount' => $value['approved_amount'] ?? null,
                    'requested_amount' => $value['requested_amount'] ?? null,
                    'is_revised' => $value['is_revised'] ?? false
                ];
            } else {
                $formatted[] = [
                    'category' => ucwords(str_replace('_', ' ', $key)),
                    'description' => '',
                    'amount' => number_format(is_numeric($value) ? $value : 0, 0, ',', '.'),
                    'raw_amount' => is_numeric($value) ? $value : 0,
                    'currency' => 'IDR',
                    'is_special_budget' => false
                ];
            }
        }
        
        return $formatted;
    }

    /**
     * Get special budget item from budget_data (jika ada)
     */
    public function getSpecialBudgetItemAttribute(): ?array
    {
        if (!$this->budget_data) return null;
        
        foreach ($this->budget_data as $item) {
            if (is_array($item) && isset($item['category']) && $item['category'] === 'Special Budget') {
                return $item;
            }
        }
        
        return null;
    }

    /**
     * Get regular budget (total budget tanpa special budget)
     */
    public function getRegularBudgetAttribute(): float
    {
        if (!$this->budget_data) return 0;
        
        $total = 0;
        foreach ($this->budget_data as $key => $value) {
            if (is_array($value)) {
                // Skip special budget
                if (isset($value['category']) && $value['category'] === 'Special Budget') {
                    continue;
                }
                $total += (float) ($value['amount'] ?? 0);
            } elseif (is_numeric($value)) {
                $total += (float) $value;
            }
        }
        
        return $total;
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk creative work yang submitted
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope untuk creative work yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk creative work yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}