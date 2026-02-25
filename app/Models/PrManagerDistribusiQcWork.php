<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrManagerDistribusiQcWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'status',
        'qc_checklist',
        'qc_results',
        'quality_score',
        'created_by',
        'reviewed_by',
        'qc_completed_at',
        'recieved_at'
    ];

    protected $casts = [
        'qc_checklist' => 'array',
        'qc_completed_at' => 'datetime',
        'recieved_at' => 'datetime',
    ];

    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return in_array($this->status, ['completed', 'approved']);
    }

    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
    }
}
