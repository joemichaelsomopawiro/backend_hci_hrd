<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtSetProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'property_name',
        'description',
        'category',
        'cost',
        'supplier',
        'status',
        'notes',
        'requested_by',
        'approved_by',
        'approved_at',
        'delivered_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cost' => 'decimal:2'
    ];

    // Relationships
    public function submission()
    {
        return $this->belongsTo(MusicSubmission::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
