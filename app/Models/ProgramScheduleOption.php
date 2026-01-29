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
        'schedule_options',      // JSON array of schedule options
        'platform',              // 'tv', 'youtube', 'website', 'all'
        'status',                // 'pending', 'approved', 'revised', 'rejected'
        'submission_notes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'selected_option_index', // Which option was approved (0-based index)
        'approved_schedule',     // Final approved schedule details (JSON)
    ];

    protected $casts = [
        'schedule_options' => 'array',
        'approved_schedule' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relationship with Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Relationship with Episode (optional, can be program-wide)
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship with User who submitted
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Relationship with User who reviewed (Distribution Manager)
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Mark as approved with selected option
     */
    public function markAsApproved(int $selectedIndex, string $reviewNotes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
            'selected_option_index' => $selectedIndex,
            'approved_schedule' => $this->schedule_options[$selectedIndex] ?? null
        ]);
    }

    /**
     * Mark as revised (needs resubmission)
     */
    public function markAsRevised(string $revisionNotes): void
    {
        $this->update([
            'status' => 'revised',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $revisionNotes
        ]);
    }

    /**
     * Mark as rejected
     */
    public function markAsRejected(string $rejectionReason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $rejectionReason
        ]);
    }
}
