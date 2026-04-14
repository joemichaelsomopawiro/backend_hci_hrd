<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrEditorWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'pr_production_work_id',
        'originally_assigned_to',
        'work_type',
        'status',
        'file_complete',
        'file_notes',
        'editing_notes',
        'file_path',
        'file_name',
        'file_size',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'file_complete' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function productionWork()
    {
        return $this->belongsTo(PrProduksiWork::class, 'pr_production_work_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'originally_assigned_to');
    }

    public function revisionNotes()
    {
        return $this->hasMany(PrEditorRevisionNote::class, 'pr_editor_work_id');
    }

    public function editorPromosiWork()
    {
        return $this->hasOne(PrEditorPromosiWork::class, 'pr_editor_work_id');
    }
}
