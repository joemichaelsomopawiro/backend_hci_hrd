<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrEpisodeCrew extends Model
{
    use HasFactory;

    protected $table = 'pr_episode_crews';

    protected $fillable = [
        'episode_id',
        'user_id',
        'role'
    ];

    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'episode_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
