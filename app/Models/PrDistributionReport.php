<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrDistributionReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pr_distribution_reports';

    protected $fillable = [
        'program_id',
        'episode_id',
        'report_title',
        'report_content',
        'distribution_data',
        'analytics_data',
        'report_period_start',
        'report_period_end',
        'status',
        'created_by'
    ];

    protected $casts = [
        'distribution_data' => 'array',
        'analytics_data' => 'array',
        'report_period_start' => 'date',
        'report_period_end' => 'date'
    ];

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PrProgram::class, 'program_id');
    }

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(PrEpisode::class, 'episode_id');
    }

    /**
     * Relationship dengan User yang create
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
