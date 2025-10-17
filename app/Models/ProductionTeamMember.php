<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'role',
        'role_notes',
        'status',
    ];

    /**
     * Relationships
     */
    public function assignment()
    {
        return $this->belongsTo(ProductionTeamAssignment::class, 'assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeLeader($query)
    {
        return $query->where('role', 'leader');
    }

    public function scopeCrew($query)
    {
        return $query->where('role', 'crew');
    }

    public function scopeTalent($query)
    {
        return $query->where('role', 'talent');
    }

    public function scopeSupport($query)
    {
        return $query->where('role', 'support');
    }

    public function scopePresent($query)
    {
        return $query->whereIn('status', ['present', 'completed']);
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    /**
     * Helpers
     */
    public function isLeader()
    {
        return $this->role === 'leader';
    }

    public function isPresent()
    {
        return in_array($this->status, ['present', 'completed']);
    }

    public function isAbsent()
    {
        return $this->status === 'absent';
    }

    public function isConfirmed()
    {
        return $this->status === 'confirmed';
    }

    public function getRoleLabel()
    {
        $labels = [
            'leader' => 'Team Leader',
            'crew' => 'Crew',
            'talent' => 'Talent',
            'support' => 'Support',
        ];

        return $labels[$this->role] ?? $this->role;
    }

    public function getStatusLabel()
    {
        $labels = [
            'assigned' => 'Assigned',
            'confirmed' => 'Confirmed',
            'present' => 'Present',
            'absent' => 'Absent',
            'completed' => 'Completed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor()
    {
        $colors = [
            'assigned' => 'blue',
            'confirmed' => 'green',
            'present' => 'green',
            'absent' => 'red',
            'completed' => 'green',
        ];

        return $colors[$this->status] ?? 'gray';
    }
}
