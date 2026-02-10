<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrEditorRevisionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_editor_work_id',
        'pr_episode_id',
        'created_by',
        'notes',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function editorWork()
    {
        return $this->belongsTo(PrEditorWork::class, 'pr_editor_work_id');
    }

    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
