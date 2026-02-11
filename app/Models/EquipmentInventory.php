<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class EquipmentInventory extends Model
{
    use HasFactory;

    protected $table = 'equipment_inventory';

    protected $fillable = [
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'description',
        'status',
        'location',
        'purchase_price',
        'purchase_date',
        'image_path',
        'is_active',
        // 'episode_id', // Not in active migration schema
        // 'assigned_to', // Not in active migration schema
        // 'assigned_by', // Not in active migration schema
        // 'assigned_at', // Not in active migration schema
        // 'return_date', // Not in active migration schema
        // 'returned_at', // Not in active migration schema
        // 'return_condition', // Not in active migration schema
        // 'return_notes', // Not in active migration schema
        // 'notes', // Not in active migration schema
        // 'created_by' // Not in active migration schema, relies on timestamps
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'return_date' => 'date',
        'returned_at' => 'datetime',
        'quantity' => 'integer'
    ];

    /**
     * Relationship dengan Episode
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Relationship dengan User yang assigned
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship dengan User yang assign
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Relationship dengan User yang create
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope untuk equipment yang available
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope untuk equipment yang assigned
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Scope untuk equipment yang returned
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Scope untuk equipment yang damaged
     */
    public function scopeDamaged($query)
    {
        return $query->where('return_condition', 'damaged');
    }

    /**
     * Scope untuk equipment yang lost
     */
    public function scopeLost($query)
    {
        return $query->where('return_condition', 'lost');
    }

    /**
     * Check if equipment is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status !== 'assigned' || !$this->return_date) {
            return false;
        }

        return Carbon::now()->isAfter($this->return_date);
    }

    /**
     * Get days until return
     */
    public function getDaysUntilReturn(): int
    {
        if ($this->status !== 'assigned' || !$this->return_date) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->return_date, false);
    }

    /**
     * Get equipment status with additional info
     */
    public function getStatusWithInfoAttribute(): string
    {
        $status = $this->status;
        
        if ($this->status === 'assigned' && $this->isOverdue()) {
            $status = 'overdue';
        }
        
        return $status;
    }

    /**
     * Get equipment condition status
     */
    public function getConditionStatusAttribute(): string
    {
        if ($this->status === 'returned') {
            return $this->return_condition ?? 'unknown';
        }
        
        return 'in_use';
    }
}