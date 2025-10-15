<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingRunSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'shooting_date',
        'location',
        'crew_list',
        'equipment_list',
        'shooting_notes',
        'status',
        'uploaded_files',
        'completion_notes',
        'created_by'
    ];

    protected $casts = [
        'crew_list' => 'array',
        'equipment_list' => 'array',
        'uploaded_files' => 'array',
        'shooting_date' => 'date'
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
     * Check if shooting can be started
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'planned';
    }

    /**
     * Check if shooting can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress' && !empty($this->uploaded_files);
    }

    /**
     * Start shooting
     */
    public function startShooting(): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->update(['status' => 'in_progress']);
        return true;
    }

    /**
     * Complete shooting
     */
    public function completeShooting($uploadedFiles, $completionNotes = null): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'uploaded_files' => $uploadedFiles,
            'completion_notes' => $completionNotes
        ]);

        return true;
    }

    /**
     * Upload shooting files
     */
    public function uploadFiles($files): bool
    {
        $this->update(['uploaded_files' => $files]);
        return true;
    }
}
