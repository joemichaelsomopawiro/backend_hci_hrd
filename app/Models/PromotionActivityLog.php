<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionActivityLog extends Model
{
    use HasFactory;

    protected $table = 'promotion_activity_logs';

    protected $fillable = [
        'promotion_work_id',
        'episode_id',
        'user_id',
        'action',
        'description',
        'changes'
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the promotion work associated with the log.
     */
    public function promotionWork(): BelongsTo
    {
        return $this->belongsTo(PromotionWork::class, 'promotion_work_id');
    }

    /**
     * Get the episode associated with the log.
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'episode_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
