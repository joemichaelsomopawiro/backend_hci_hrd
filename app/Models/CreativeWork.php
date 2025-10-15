<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreativeWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_submission_id',
        'created_by',
        'script_content',
        'storyboard_file_path',
        'storyboard_file_name',
        'storyboard_file_size',
        'creative_notes',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'script_approved',
        'storyboard_approved',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'script_approved' => 'boolean',
        'storyboard_approved' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function musicSubmission()
    {
        return $this->belongsTo(MusicSubmission::class, 'music_submission_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'under_review']);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Helpers
     */
    public function isApproved()
    {
        return $this->status === 'approved' && $this->script_approved && $this->storyboard_approved;
    }

    public function isPending()
    {
        return in_array($this->status, ['draft', 'submitted', 'under_review']);
    }

    public function getStoryboardUrl()
    {
        if ($this->storyboard_file_path) {
            return asset('storage/' . $this->storyboard_file_path);
        }
        return null;
    }

    public function getStoryboardSizeFormatted()
    {
        if ($this->storyboard_file_size) {
            $size = $this->storyboard_file_size;
            if ($size < 1024) {
                return $size . ' B';
            } elseif ($size < 1024 * 1024) {
                return round($size / 1024, 2) . ' KB';
            } else {
                return round($size / (1024 * 1024), 2) . ' MB';
            }
        }
        return null;
    }

    public function getStatusLabel()
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revision' => 'Needs Revision',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'draft' => 'gray',
            'submitted' => 'blue',
            'under_review' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'revision' => 'orange',
        ];

        return $colors[$this->status] ?? 'gray';
    }
}






