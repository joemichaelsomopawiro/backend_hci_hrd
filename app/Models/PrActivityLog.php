<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrActivityLog extends Model
{
    use HasFactory;

    protected $table = 'pr_activity_logs';

    protected $fillable = [
        'program_id',
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
     * Get the program associated with the log.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PrProgram::class, 'program_id');
    }

    /**
     * Get the episode associated with the log.
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(PrEpisode::class, 'episode_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
