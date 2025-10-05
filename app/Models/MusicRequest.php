<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MusicRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'song_id',
        'music_arranger_id',
        'proposed_singer_id',
        'producer_id',
        'approved_singer_id',
        'status',
        'arrangement_notes',
        'producer_notes',
        'requested_date',
        'approved_date',
        'completed_date',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'completed_at'
    ];

    protected $casts = [
        'requested_date' => 'date',
        'approved_date' => 'date',
        'completed_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship dengan Song
     */
    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    /**
     * Relationship dengan Music Arranger (User)
     */
    public function musicArranger()
    {
        return $this->belongsTo(User::class, 'music_arranger_id');
    }

    /**
     * Relationship dengan Proposed Singer (User)
     */
    public function proposedSinger()
    {
        return $this->belongsTo(User::class, 'proposed_singer_id');
    }

    /**
     * Relationship dengan Producer (User)
     */
    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * Relationship dengan Approved Singer (User)
     */
    public function approvedSinger()
    {
        return $this->belongsTo(User::class, 'approved_singer_id');
    }

    /**
     * Scope untuk request berdasarkan Music Arranger
     */
    public function scopeByMusicArranger($query, $musicArrangerId)
    {
        return $query->where('music_arranger_id', $musicArrangerId);
    }

    /**
     * Scope untuk request berdasarkan Producer
     */
    public function scopeByProducer($query, $producerId)
    {
        return $query->where('producer_id', $producerId);
    }

    /**
     * Scope untuk request berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk pending requests (submitted dan reviewed)
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'reviewed']);
    }

    /**
     * Submit request (Music Arranger action)
     */
    public function submit()
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);
    }

    /**
     * Review request (Producer action)
     */
    public function review($producerId)
    {
        $this->update([
            'status' => 'reviewed',
            'producer_id' => $producerId,
            'reviewed_at' => now()
        ]);
    }

    /**
     * Approve request (Producer action)
     */
    public function approve($producerId, $approvedSingerId = null, $producerNotes = null)
    {
        $this->update([
            'status' => 'approved',
            'producer_id' => $producerId,
            'approved_singer_id' => $approvedSingerId ?? $this->proposed_singer_id,
            'producer_notes' => $producerNotes,
            'approved_at' => now(),
            'approved_date' => now()->toDateString()
        ]);
    }

    /**
     * Reject request (Producer action)
     */
    public function reject($producerId, $producerNotes = null)
    {
        $this->update([
            'status' => 'rejected',
            'producer_id' => $producerId,
            'producer_notes' => $producerNotes,
            'rejected_at' => now()
        ]);
    }

    /**
     * Complete request
     */
    public function complete()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_date' => now()->toDateString()
        ]);
    }

    /**
     * Check if request can be approved
     */
    public function canBeApproved()
    {
        return in_array($this->status, ['submitted', 'reviewed']);
    }

    /**
     * Check if request can be rejected
     */
    public function canBeRejected()
    {
        return in_array($this->status, ['submitted', 'reviewed']);
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'submitted' => 'blue',
            'reviewed' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'in_progress' => 'purple',
            'completed' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get status label in Indonesian
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'submitted' => 'Diajukan',
            'reviewed' => 'Sedang Direview',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'in_progress' => 'Sedang Dikerjakan',
            'completed' => 'Selesai',
            default => 'Unknown'
        };
    }
}


