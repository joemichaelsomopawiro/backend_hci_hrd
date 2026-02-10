<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrDesignGrafisWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_episode_id',
        'pr_production_work_id',
        'pr_promotion_work_id',
        'assigned_to',
        'status',
        'youtube_thumbnail_link',
        'bts_thumbnail_link',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function productionWork()
    {
        return $this->belongsTo(PrProduksiWork::class, 'pr_production_work_id');
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
