<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SocialMediaPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'promotion_work_id',
        'created_by',
        'platform',
        'post_id',
        'title',
        'description',
        'content',
        'hashtags',
        'tags',
        'mentions',
        'media_files',
        'thumbnail_url',
        'post_url',
        'status',
        'scheduled_at',
        'published_at',
        'engagement_metrics',
        'notes',
        'proof_file_path',
        'proof_file_link', // New: External link for proof
        'proof_file_name',
        'proof_type',
        'proof_notes',
        'proof_submitted_at'
    ];

    protected $casts = [
        'hashtags' => 'array',
        'tags' => 'array',
        'mentions' => 'array',
        'media_files' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'engagement_metrics' => 'array',
        'proof_submitted_at' => 'datetime'
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
     * Scope untuk posts yang draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope untuk posts yang scheduled
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope untuk posts yang published
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope untuk posts yang failed
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope untuk posts berdasarkan platform
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope untuk posts berdasarkan content type
     */
    public function scopeByContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope untuk posts yang ready to publish
     */
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_time', '<=', now());
    }

    /**
     * Check if post is ready to publish
     */
    public function isReadyToPublish(): bool
    {
        return $this->status === 'scheduled' && 
               $this->scheduled_time && 
               Carbon::now()->isAfter($this->scheduled_time);
    }

    /**
     * Get hashtags as string
     */
    public function getHashtagsStringAttribute(): string
    {
        if (!$this->hashtags || !is_array($this->hashtags)) {
            return '';
        }

        return implode(' ', array_map(function($tag) {
            return str_starts_with($tag, '#') ? $tag : '#' . $tag;
        }, $this->hashtags));
    }

    /**
     * Get platform display name
     */
    public function getPlatformDisplayNameAttribute(): string
    {
        $platforms = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'website' => 'Website'
        ];

        return $platforms[$this->platform] ?? ucfirst($this->platform);
    }

    /**
     * Get content type display name
     */
    public function getContentTypeDisplayNameAttribute(): string
    {
        $types = [
            'story' => 'Story',
            'reels' => 'Reels',
            'post' => 'Post',
            'highlight' => 'Highlight'
        ];

        return $types[$this->content_type] ?? ucfirst($this->content_type);
    }

    /**
     * Get engagement rate
     */
    public function getEngagementRateAttribute(): float
    {
        if (!$this->engagement_data || !isset($this->engagement_data['views'])) {
            return 0.0;
        }

        $views = $this->engagement_data['views'];
        $likes = $this->engagement_data['likes'] ?? 0;
        $comments = $this->engagement_data['comments'] ?? 0;
        $shares = $this->engagement_data['shares'] ?? 0;

        if ($views === 0) {
            return 0.0;
        }

        return round((($likes + $comments + $shares) / $views) * 100, 2);
    }

    /**
     * Get time until publish
     */
    public function getTimeUntilPublishAttribute(): string
    {
        if (!$this->scheduled_time || $this->status !== 'scheduled') {
            return '';
        }

        $now = Carbon::now();
        $scheduled = Carbon::parse($this->scheduled_time);

        if ($now->isAfter($scheduled)) {
            return 'Ready to publish';
        }

        return $now->diffForHumans($scheduled, true) . ' remaining';
    }
}