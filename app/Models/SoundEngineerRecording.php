<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoundEngineerRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'recording_date',
        'recording_location',
        'equipment_list',
        'audio_files',
        'recording_notes',
        'status',
        'completion_notes',
        'created_by'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'audio_files' => 'array',
        'recording_date' => 'date'
    ];

    /**
     * Relasi dengan Music Submission
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MusicSubmission::class);
    }

    /**
     * Relasi dengan User (Created By)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if recording can be started
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if recording can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress' && !empty($this->audio_files);
    }

    /**
     * Start recording
     */
    public function startRecording(): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->update(['status' => 'in_progress']);
        return true;
    }

    /**
     * Complete recording
     */
    public function completeRecording($audioFiles, $completionNotes = null): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'audio_files' => $audioFiles,
            'completion_notes' => $completionNotes
        ]);

        return true;
    }

    /**
     * Upload audio files
     */
    public function uploadAudioFiles($files): bool
    {
        $this->update(['audio_files' => $files]);
        return true;
    }
}
