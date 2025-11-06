<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'created_by',
        'work_type',
        'title',
        'description',
        'content_plan',
        'talent_data',
        'location_data',
        'equipment_needed',
        'shooting_date',
        'shooting_time',
        'shooting_notes',
        'file_paths',
        'social_media_links',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes'
    ];

    protected $casts = [
        'talent_data' => 'array',
        'location_data' => 'array',
        'equipment_needed' => 'array',
        'file_paths' => 'array',
        'social_media_links' => 'array',
        'shooting_date' => 'date',
        'shooting_time' => 'datetime:H:i',
        'reviewed_at' => 'datetime'
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
     * Relationship dengan Social Media Posts
     */
    public function socialMediaPosts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class);
    }

    /**
     * Scope untuk work yang planning
     */
    public function scopePlanning($query)
    {
        return $query->where('status', 'planning');
    }

    /**
     * Scope untuk work yang shooting
     */
    public function scopeShooting($query)
    {
        return $query->where('status', 'shooting');
    }

    /**
     * Scope untuk work yang published
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope berdasarkan work type
     */
    public function scopeByWorkType($query, $workType)
    {
        return $query->where('work_type', $workType);
    }

    /**
     * Check if work is ready for shooting
     */
    public function isReadyForShooting(): bool
    {
        return $this->status === 'planning' && 
               $this->shooting_date && 
               $this->shooting_time &&
               $this->talent_data &&
               $this->location_data;
    }

    /**
     * Mark as shooting
     */
    public function markAsShooting(): void
    {
        $this->update(['status' => 'shooting']);
    }

    /**
     * Mark as editing
     */
    public function markAsEditing(): void
    {
        $this->update(['status' => 'editing']);
    }

    /**
     * Mark as published
     */
    public function markAsPublished(): void
    {
        $this->update(['status' => 'published']);
    }
}













