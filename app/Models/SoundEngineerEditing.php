<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoundEngineerEditing extends Model
{
    use HasFactory;

    protected $table = 'sound_engineer_editing';

    protected $fillable = [
        'episode_id',
        'sound_engineer_recording_id',
        'sound_engineer_id',
        'vocal_file_path',
        'final_file_path',
        'vocal_file_link',      // New: External storage link for vocal file
        'final_file_link',       // New: External storage link for final file
        'editing_notes',
        'submission_notes',
        'status',
        'estimated_completion',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejected_by',
        'rejection_reason',
        'created_by'
    ];

    protected $casts = [
        'estimated_completion' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    /**
     * Relationship: Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship: Sound Engineer Recording
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(SoundEngineerRecording::class, 'sound_engineer_recording_id');
    }

    /**
     * Relationship: Sound Engineer
     */
    public function soundEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sound_engineer_id');
    }

    /**
     * Relationship: Approved By
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Rejected By
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Relationship: Created By
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}









