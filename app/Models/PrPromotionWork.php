<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrPromotionWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'work_type',
        'shooting_date',
        'shooting_time',
        'location_data',
        'shooting_notes',
        'file_paths',
        'sharing_proof',
        'status',
        'title',
        'description',
        'created_by',
        'completion_notes',
    ];

    protected $casts = [
        'shooting_date' => 'date',
        'location_data' => 'array',
        'file_paths' => 'array',
        'sharing_proof' => 'array',
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
}
