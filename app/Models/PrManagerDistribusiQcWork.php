<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PrEditorWork;
use App\Models\PrDesignGrafisWork;

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

    protected $appends = [
        'editor_file_path',
        'episode_poster_link'
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

    /**
     * Get Editor's File Path for QC
     * Virtual attribute for frontend access
     */
    public function getEditorFilePathAttribute()
    {
        // Try to get the main episode file from editor work
        $editorWork = PrEditorWork::where('pr_episode_id', $this->pr_episode_id)
            ->where('work_type', 'main_episode')
            ->first();

        return $editorWork ? $editorWork->file_path : null;
    }

    /**
     * Get Graphic Design Episode Poster for QC
     * Virtual attribute for frontend access
     */
    public function getEpisodePosterLinkAttribute()
    {
        $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $this->pr_episode_id)->first();
        return $designGrafisWork ? $designGrafisWork->episode_poster_link : null;
    }
}
