<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionEquipment extends Model
{
    use HasFactory;

    /** Tabel di database: production_equipment (singular) */
    protected $table = 'production_equipment';

    protected $appends = [
        'equipment_items',
        'formatted_equipment_list',
    ];

    protected $fillable = [
        'episode_id',
        'program_id',
        'request_group_id',
        'equipment_list',
        'equipment_quantities',
        'request_notes',
        'scheduled_date',
        'scheduled_time',
        'status',
        'requested_by',
        'crew_leader_id',
        'crew_member_ids',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_notes',
        'rejection_reason',
        'assigned_at',
        'returned_at',
        'return_condition',
        'return_notes',
        'assigned_to',
        'returned_by',
        'team_type'
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'equipment_quantities' => 'array',
        'crew_member_ids' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
        'scheduled_date' => 'date',
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan Program (denormalized for listing)
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Crew leader (1) - tim setting yang pinjam
     */
    public function crewLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crew_leader_id');
    }

    /**
     * User yang mengembalikan (tim syuting)
     */
    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * Relationship dengan User yang request (Alias for compatibility)
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship dengan User yang request
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship dengan User yang approve
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship dengan User yang reject
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Relationship dengan User yang assigned
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Approve equipment request
     */
    public function approve(int $approvedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    /**
     * Reject equipment request
     */
    public function reject(int $rejectedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Assign equipment
     */
    public function assign(int $assignedTo): void
    {
        $this->update([
            'status' => 'in_use',
            'assigned_to' => $assignedTo,
            'assigned_at' => now()
        ]);
    }

    /**
     * Return equipment
     */
    public function return(): void
    {
        $this->update([
            'status' => 'returned',
            'returned_at' => now()
        ]);
    }

    /**
     * Get equipment list as formatted string
     */
    public function getFormattedEquipmentListAttribute(): string
    {
        if (!$this->equipment_list) return 'N/A';
        
        return implode(', ', $this->equipment_list);
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk request yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk request yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk request yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope untuk equipment yang in use
     */
    public function scopeInUse($query)
    {
        return $query->where('status', 'in_use');
    }

    /**
     * Scope untuk equipment yang returned
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Riwayat transfer (pindah ke episode lain tanpa return)
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(ProductionEquipmentTransfer::class, 'production_equipment_id');
    }

    /**
     * Equipment list as name => qty for display
     */
    public function getEquipmentItemsAttribute(): array
    {
        if (!is_array($this->equipment_list)) {
            return [];
        }
        $counts = array_count_values($this->equipment_list);
        $items = [];
        foreach ($counts as $name => $qty) {
            $items[] = ['name' => $name, 'qty' => $qty];
        }
        return $items;
    }
}