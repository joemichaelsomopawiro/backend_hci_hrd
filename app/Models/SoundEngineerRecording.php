<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SoundEngineerRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'music_arrangement_id',
        'recording_notes',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'file_link',            // New: External storage link (Google Drive, etc.)
        'equipment_used',
        'status',
        'recording_schedule',
        'recording_started_at',
        'recording_completed_at',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes'
    ];

    protected $casts = [
        'equipment_used' => 'array',
        'recording_schedule' => 'datetime',
        'recording_started_at' => 'datetime',
        'recording_completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan Music Arrangement
     */
    public function musicArrangement(): BelongsTo
    {
        return $this->belongsTo(MusicArrangement::class);
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
     * Relationship dengan Sound Engineer Editing
     */
    public function editing(): HasOne
    {
        return $this->hasOne(SoundEngineerEditing::class, 'sound_engineer_recording_id');
    }

    /**
     * Relationship dengan Equipment Requests (via Episode)
     */
    public function equipmentRequests()
    {
        return $this->hasMany(ProductionEquipment::class, 'episode_id', 'episode_id');
    }

    /**
     * Scope untuk recording yang sudah direview
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope untuk recording yang belum direview
     */
    public function scopeNotReviewed($query)
    {
        return $query->where('status', 'completed')->whereNull('reviewed_by');
    }

    /**
     * Check if recording has been reviewed
     */
    public function hasBeenReviewed(): bool
    {
        return $this->status === 'reviewed' && $this->reviewed_by !== null;
    }


    /**
     * Start recording
     */
    public function startRecording(): void
    {
        $this->update([
            'status' => 'recording',
            'recording_started_at' => now()
        ]);
    }

    /**
     * Complete recording
     */
    public function completeRecording(): void
    {
        $this->update([
            'status' => 'completed',
            'recording_completed_at' => now()
        ]);
    }

    /**
     * Review recording
     */
    public function review(int $reviewedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
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
     * Get formatted equipment list
     */
    public function getFormattedEquipmentListAttribute(): string
    {
        if (!$this->equipment_used) return 'N/A';
        
        return implode(', ', $this->equipment_used);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk recording yang draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope untuk recording yang recording
     */
    public function scopeRecording($query)
    {
        return $query->where('status', 'recording');
    }

    /**
     * Scope untuk recording yang completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}