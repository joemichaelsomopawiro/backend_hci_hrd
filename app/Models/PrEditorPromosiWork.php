<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrEditorPromosiWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_episode_id',
        'pr_editor_work_id',
        'pr_promotion_work_id',
        'assigned_to',
        'status',
        'bts_video_link',
        'tv_ad_link',
        'ig_highlight_link',
        'tv_highlight_link',
        'fb_highlight_link',
        'notes',
        'started_at',
        'completed_at',
        'submitted_at',
        'deadline',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function editorWork()
    {
        return $this->belongsTo(PrEditorWork::class, 'pr_editor_work_id');
    }

    public function promotionWork()
    {
        return $this->belongsTo(PrPromotionWork::class, 'pr_promotion_work_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
