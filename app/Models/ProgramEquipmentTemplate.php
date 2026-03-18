<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEquipmentTemplate extends Model
{
    protected $fillable = [
        'program_id',
        'name',
        'items', // [{"name":"Sony A7iv","qty":2}, ...]
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
