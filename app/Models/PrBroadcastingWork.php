<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrBroadcastingWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'work_type',
        'youtube_url',
        'youtube_video_id',
        'title',
        'description',
        'metadata',
        'website_url',
        'thumbnail_path',
        'video_file_path',
        'playlist_data',
        'scheduled_time',
        'status',
        'created_by',
        'published_at',
        'notes',
    ];

    protected $casts = [
        'metadata' => 'array',
        'playlist_data' => 'array',
        'scheduled_time' => 'datetime',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper Methods
    public function markAsPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now()
        ]);
    }
}
