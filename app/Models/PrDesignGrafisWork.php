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
        'deadline',
        'started_at',
        'submitted_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'deadline' => 'datetime',
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

    // Calculate deadline as 1 day before air_date
    public function calculateDeadline()
    {
        if ($this->episode && $this->episode->air_date) {
            $this->deadline = \Carbon\Carbon::parse($this->episode->air_date)->subDay();
        }
    }

    // Boot method to auto-calculate deadline
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($work) {
            $work->calculateDeadline();
        });
    }
}
