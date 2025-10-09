<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramProposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_regular_id',
        'spreadsheet_id',
        'spreadsheet_url',
        'sheet_name',
        'proposal_title',
        'proposal_description',
        'format_type',
        'kwartal_data',
        'schedule_options',
        'status',
        'last_synced_at',
        'auto_sync',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_by'
    ];

    protected $casts = [
        'kwartal_data' => 'array',
        'schedule_options' => 'array',
        'last_synced_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'auto_sync' => 'boolean'
    ];

    /**
     * Relasi dengan Program Regular
     */
    public function programRegular(): BelongsTo
    {
        return $this->belongsTo(ProgramRegular::class);
    }

    /**
     * Relasi dengan User (Reviewed By)
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Relasi dengan User (Created By)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get full Google Spreadsheet URL
     */
    public function getFullSpreadsheetUrlAttribute(): string
    {
        if ($this->spreadsheet_url) {
            return $this->spreadsheet_url;
        }

        return "https://docs.google.com/spreadsheets/d/{$this->spreadsheet_id}/edit";
    }

    /**
     * Get embedded spreadsheet URL
     */
    public function getEmbeddedUrlAttribute(): string
    {
        return "https://docs.google.com/spreadsheets/d/{$this->spreadsheet_id}/edit?widget=true&headers=false&embedded=true";
    }

    /**
     * Sync data from Google Spreadsheet
     * 
     * TODO: Implement Google Sheets API integration
     */
    public function syncFromSpreadsheet(): bool
    {
        // TODO: Implement actual Google Sheets API sync
        // For now, just update last_synced_at
        
        $this->update([
            'last_synced_at' => now()
        ]);

        return true;
    }

    /**
     * Check if sync is needed (last sync > 1 hour ago)
     */
    public function needsSync(): bool
    {
        if (!$this->auto_sync) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->diffInHours(now()) > 1;
    }

    /**
     * Submit proposal for review
     */
    public function submitForReview(): void
    {
        $this->update([
            'status' => 'submitted'
        ]);
    }

    /**
     * Mark as under review
     */
    public function markAsUnderReview(int $reviewerId): void
    {
        $this->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now()
        ]);
    }

    /**
     * Approve proposal
     */
    public function approve(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Reject proposal
     */
    public function reject(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Request revision
     */
    public function requestRevision(int $reviewerId, string $notes): void
    {
        $this->update([
            'status' => 'needs_revision',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Needs sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->where('auto_sync', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subHour());
            });
    }
}

