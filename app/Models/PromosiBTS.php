<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromosiBTS extends Model
{
    use HasFactory;

    protected $table = 'promosi_bts';

    protected $fillable = [
        'submission_id',
        'bts_video_path',
        'bts_video_url',
        'talent_photos',
        'shooting_schedule_id',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'talent_photos' => 'array'
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
     * Relasi dengan Shooting Run Sheet
     */
    public function shootingSchedule(): BelongsTo
    {
        return $this->belongsTo(ShootingRunSheet::class, 'shooting_schedule_id');
    }

    /**
     * Check if BTS can be started
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if BTS can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress' && 
               $this->bts_video_path && 
               !empty($this->talent_photos);
    }

    /**
     * Start BTS work
     */
    public function startWork(): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->update(['status' => 'in_progress']);
        return true;
    }

    /**
     * Complete BTS work
     */
    public function completeWork(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update(['status' => 'completed']);
        return true;
    }

    /**
     * Upload BTS video
     */
    public function uploadBTSVideo($videoPath, $videoUrl): bool
    {
        $this->update([
            'bts_video_path' => $videoPath,
            'bts_video_url' => $videoUrl
        ]);

        return true;
    }

    /**
     * Upload talent photos
     */
    public function uploadTalentPhotos($photos): bool
    {
        $this->update(['talent_photos' => $photos]);
        return true;
    }
}
