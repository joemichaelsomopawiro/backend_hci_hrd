<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramScheduleOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'episode_id',
        'submitted_by',
        'schedule_options',
        'platform',
        'status',
        'selected_option_index',
        'selected_schedule_date',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'submission_notes',
        'rejection_reason'
    ];

    protected $casts = [
        'schedule_options' => 'array',
        'selected_schedule_date' => 'datetime',
        'reviewed_at' => 'datetime'
    ];

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang submit (Manager Program)
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Relationship dengan User yang review (Manager Broadcasting)
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get selected option
     */
    public function getSelectedOptionAttribute(): ?array
    {
        if ($this->selected_option_index === null || !isset($this->schedule_options[$this->selected_option_index])) {
            return null;
        }

        return $this->schedule_options[$this->selected_option_index];
    }

    /**
     * Check if option is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if option is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if option is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Menunggu Review',
            'reviewing' => 'Sedang Direview',
            'approved' => 'Diterima',
            'rejected' => 'Ditolak',
            'expired' => 'Kadaluarsa'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}

















