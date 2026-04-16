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
        'file_link',            // New: External storage link (Google Drive, etc.)
        'file_path',            // Kept for backward compatibility
        'file_name',            // Kept for backward compatibility
        'file_size',            // Kept for backward compatibility
        'mime_type',            // Kept for backward compatibility
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
        'sound_engineer_help_file_link', // New: Link for help file from SE
        'needs_sound_engineer_help',
        'is_group',
        'group_name',
        'group_members',
        'song_approved_at',
        'arrangement_submitted_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'song_approved_at' => 'datetime',
        'arrangement_submitted_at' => 'datetime',
        'producer_modified_at' => 'datetime',
        'sound_engineer_help_at' => 'datetime',
        'file_size' => 'integer',
        'producer_modified' => 'boolean',
        'needs_sound_engineer_help' => 'boolean',
        'is_group' => 'boolean',
        'group_members' => 'array'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['file_url', 'episode_display', 'group_members_list', 'group_full_display', 'singer_display'];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Teks siap tampil untuk episode (nama/judul, bukan ID).
     * Gunakan ini di frontend agar tidak menampilkan "Episode #3478" (id) melainkan "Ep. 1 – Judul Episode".
     * Hanya terisi ketika relation episode di-load (e.g. with('episode')).
     */
    public function getEpisodeDisplayAttribute(): ?string
    {
        if (! $this->relationLoaded('episode') || ! $this->episode) {
            return null;
        }
        $e = $this->episode;
        $num = $e->episode_number ?? null;
        $title = trim((string) ($e->title ?? ''));
        if ($num !== null && $title !== '') {
            return "Ep. {$num} – {$title}";
        }
        if ($num !== null) {
            return "Episode {$num}";
        }
        if ($title !== '') {
            return $title;
        }
        return null;
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
     * Relationship dengan Singer
     * Updated: menggunakan Singer model, bukan User
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Singer::class, 'singer_id');
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

        // If Producer modified song/singer/group, use modified values
        if ($this->producer_modified) {
            if ($this->producer_modified_song_title) {
                $updateData['song_title'] = $this->producer_modified_song_title;
            }
            if ($this->producer_modified_singer_name) {
                $updateData['singer_name'] = $this->producer_modified_singer_name;
            }
            
            // Note: is_group, group_name, and group_members are updated immediately in producerModify
        }

        $this->update($updateData);
    }

    /**
     * Producer modify song/singer before approve
     */
    public function producerModify(
        ?string $newSongTitle = null, 
        ?string $newSingerName = null, 
        ?int $songId = null, 
        ?int $singerId = null,
        bool $isGroup = false,
        ?string $groupName = null,
        $groupMembers = null
    ): void
    {
        // Store original values if not already stored
        if (!$this->original_song_title) {
            $this->original_song_title = $this->song_title;
        }
        if (!$this->original_singer_name) {
            $this->original_singer_name = $this->singer_name;
        }

        // Determine new values (use provided or keep existing)
        $finalSongTitle = $newSongTitle ?? $this->song_title;
        $finalSingerName = $newSingerName ?? $this->singer_name;
        $finalSongId = $songId ?? $this->song_id;
        $finalSingerId = $singerId ?? $this->singer_id;

        // Update both the main fields (for immediate display) and producer_modified fields (for tracking)
        $this->update([
            // Update main fields immediately so Producer can see the change
            'song_title' => $finalSongTitle,
            'singer_name' => $finalSingerName,
            'song_id' => $finalSongId,
            'singer_id' => $finalSingerId,
            'is_group' => $isGroup,
            'group_name' => $isGroup ? ($groupName ?? $this->group_name) : null,
            'group_members' => $isGroup ? ($groupMembers ?? $this->group_members) : null,
            // Also store in producer_modified fields for tracking and approval workflow
            'producer_modified_song_title' => $finalSongTitle,
            'producer_modified_singer_name' => $finalSingerName,
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
     * Get file URL dengan signed URL
     * Menggunakan temporary signed route agar bisa diakses tanpa Authorization header
     * Bisa langsung dipakai di <audio> tag dan <a href> download
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        
        // Generate temporary signed URL (expire dalam 24 jam)
        // Signed URL tidak perlu Authorization header, bisa langsung dipakai di browser
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'music-arrangement.file',
            now()->addHours(24),
            ['id' => $this->id]
        );
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

    /**
     * Get member names as a comma-separated string.
     */
    public function getGroupMembersListAttribute(): ?string
    {
        if (!$this->is_group || !$this->group_members || !is_array($this->group_members)) {
            return null;
        }
        return implode(', ', $this->group_members);
    }

    /**
     * Get full group display: "Group Name (Member 1, Member 2, ...)"
     */
    public function getGroupFullDisplayAttribute(): ?string
    {
        if (!$this->is_group) {
            return null;
        }
        
        $name = $this->group_name ?? 'Unnamed Group';
        $members = $this->group_members_list;
        
        return $members ? "{$name} ({$members})" : $name;
    }

    /**
     * Get singer display: group_name if is_group, otherwise singer_name
     */
    public function getSingerDisplayAttribute(): ?string
    {
        if ($this->is_group) {
            return $this->group_name;
        }
        return $this->singer_name;
    }
}
