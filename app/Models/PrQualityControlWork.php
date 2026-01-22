<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrQualityControlWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'qc_type',
        'editor_promosi_file_locations',
        'design_grafis_file_locations',
        'qc_checklist',
        'qc_results',
        'quality_score',
        'qc_notes',
        'issues_found',
        'improvements_needed',
        'screenshots',
        'status',
        'created_by',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'qc_completed_at',
    ];

    protected $casts = [
        'editor_promosi_file_locations' => 'array',
        'design_grafis_file_locations' => 'array',
        'qc_checklist' => 'array',
        'qc_results' => 'array',
        'issues_found' => 'array',
        'improvements_needed' => 'array',
        'screenshots' => 'array',
        'reviewed_at' => 'datetime',
        'qc_completed_at' => 'datetime',
    ];

    // Relationships
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

    // Helper Methods
    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markAsApproved(): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => now()
        ]);
    }
}
