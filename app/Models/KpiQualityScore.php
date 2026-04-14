<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiQualityScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'pr_episode_id',
        'music_episode_id',
        'program_type',
        'workflow_step',
        'quality_score',
        'notes',
        'scored_by',
    ];

    protected $casts = [
        'quality_score' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function prEpisode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function musicEpisode()
    {
        return $this->belongsTo(Episode::class, 'music_episode_id');
    }

    public function scoredBy()
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
