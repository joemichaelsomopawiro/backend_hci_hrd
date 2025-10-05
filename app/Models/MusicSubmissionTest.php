<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicSubmissionTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_arranger_id',
        'song_id',
        'proposed_singer_id',
        'arrangement_notes',
        'requested_date',
        'submission_status',
        'current_state'
    ];
}





