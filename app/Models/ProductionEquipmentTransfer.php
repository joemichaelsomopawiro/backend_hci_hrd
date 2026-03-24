<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEquipmentTransfer extends Model
{
    protected $fillable = [
        'production_equipment_id',
        'from_episode_id',
        'to_episode_id',
        'to_user_id',
        'transferred_by',
        'transferred_at',
        'notes',
        'status',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function productionEquipment(): BelongsTo
    {
        return $this->belongsTo(ProductionEquipment::class);
    }

    public function fromEpisode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'from_episode_id');
    }

    public function toEpisode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'to_episode_id');
    }

    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
