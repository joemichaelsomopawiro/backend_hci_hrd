<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrProgramTeamPreset extends Model
{
    use HasFactory;

    protected $table = 'pr_program_team_presets';

    protected $fillable = [
        'manager_program_id',
        'name',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    /**
     * Relationship dengan Manager Program (User)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_program_id');
    }
}
