<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicArrangement extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'song_id',
        'singer_id',
        'song_title',
        'singer_name',
        'original_song_title',
        'original_singer_name',
        'producer_modified_song_title',
        'producer_modified_singer_name',
        'producer_modified',
        'producer_modified_at',
        'arrangement_notes',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'submitted_at',
        'sound_engineer_helper_id',
        'sound_engineer_help_notes',
        'sound_engineer_help_at',
        'needs_sound_engineer_help'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'producer_modified_at' => 'datetime',
        'sound_engineer_help_at' => 'datetime',
        'file_size' => 'integer',
        'producer_modified' => 'boolean',
        'needs_sound_engineer_help' => 'boolean'
    ];

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
     * Relationship dengan Song (optional)
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Song::class);
    }

    /**
     * Relationship dengan Singer (User)
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'singer_id');
    }

    /**
     * Relationship dengan Sound Engineer Helper
     */
    public function soundEngineerHelper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sound_engineer_helper_id');
    }

    /**
     * Relationship dengan Sound Engineer Recordings
     */
    public function soundEngineerRecording(): HasMany
    {
        return $this->hasMany(SoundEngineerRecording::class);
    }

    /**
     * Submit arrangement for review
     */
    public function submitForReview(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);
    }

    /**
     * Approve arrangement
     * Uses producer_modified values if available, otherwise uses original values
     */
    public function approve(int $reviewedBy, ?string $notes = null): void
    {
        $updateData = [
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ];

        // If Producer modified song/singer, use modified values
        if ($this->producer_modified) {
            if ($this->producer_modified_song_title) {
                $updateData['song_title'] = $this->producer_modified_song_title;
            }
            if ($this->producer_modified_singer_name) {
                $updateData['singer_name'] = $this->producer_modified_singer_name;
            }
        }

        $this->update($updateData);
    }

    /**
     * Producer modify song/singer before approve
     */
    public function producerModify(?string $newSongTitle = null, ?string $newSingerName = null, ?int $songId = null, ?int $singerId = null): void
    {
        // Store original values if not already stored
        if (!$this->original_song_title) {
            $this->original_song_title = $this->song_title;
        }
        if (!$this->original_singer_name) {
            $this->original_singer_name = $this->singer_name;
        }

        $this->update([
            'producer_modified_song_title' => $newSongTitle ?? $this->song_title,
            'producer_modified_singer_name' => $newSingerName ?? $this->singer_name,
            'song_id' => $songId ?? $this->song_id,
            'singer_id' => $singerId ?? $this->singer_id,
            'producer_modified' => true,
            'producer_modified_at' => now()
        ]);
    }

    /**
     * Request Sound Engineer help for rejected arrangement
     */
    public function requestSoundEngineerHelp(int $soundEngineerId, string $helpNotes): void
    {
        $this->update([
            'needs_sound_engineer_help' => true,
            'sound_engineer_helper_id' => $soundEngineerId,
            'sound_engineer_help_notes' => $helpNotes,
            'sound_engineer_help_at' => now()
            // Keep status as 'rejected' but mark as needing help
        ]);
    }

    /**
     * Reject arrangement
     */
    public function reject(int $reviewedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'needs_sound_engineer_help' => true // Mark as needing help when rejected
        ]);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk arrangement yang submitted
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope untuk arrangement yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk arrangement yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
