<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEquipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'brand',
        'model',
        'serial_number',
        'status',
        'assigned_to',
        'program_id',
        'episode_id',
        'last_maintenance',
        'next_maintenance',
        'notes',
        'specifications',
    ];

    protected $casts = [
        'last_maintenance' => 'date',
        'next_maintenance' => 'date',
        'specifications' => 'array',
    ];

    // Relasi dengan User (assigned to)
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Relasi dengan Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Relasi dengan Episode
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    // Scope untuk equipment berdasarkan status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk equipment berdasarkan kategori
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Scope untuk equipment yang tersedia
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Scope untuk equipment yang sedang digunakan
    public function scopeInUse($query)
    {
        return $query->where('status', 'in_use');
    }

    // Scope untuk equipment yang perlu maintenance
    public function scopeNeedsMaintenance($query)
    {
        return $query->where('next_maintenance', '<=', now()->addDays(7))
                    ->where('status', '!=', 'retired');
    }

    // Method untuk mendapatkan status equipment
    public function getStatusAttribute($value)
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    // Method untuk mengecek apakah equipment tersedia
    public function isAvailable()
    {
        return $this->status === 'available';
    }

    // Method untuk mengecek apakah equipment perlu maintenance
    public function needsMaintenance()
    {
        return $this->next_maintenance && $this->next_maintenance <= now()->addDays(7);
    }

    // Method untuk mendapatkan hari sampai maintenance
    public function getDaysUntilMaintenanceAttribute()
    {
        if (!$this->next_maintenance) {
            return null;
        }
        
        return now()->diffInDays($this->next_maintenance, false);
    }

    // Method untuk mendapatkan full name equipment
    public function getFullNameAttribute()
    {
        $parts = array_filter([$this->brand, $this->model, $this->name]);
        return implode(' ', $parts);
    }
}
