<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'description',
        'access_level',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationship dengan user yang membuat role
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope untuk role yang aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope berdasarkan access level
    public function scopeByAccessLevel($query, $level)
    {
        return $query->where('access_level', $level);
    }

    // Method untuk mengecek apakah role memiliki akses tertentu
    public function hasEmployeeAccess()
    {
        return in_array($this->access_level, ['employee', 'manager', 'hr_readonly', 'hr_full']);
    }

    public function hasManagerAccess()
    {
        return in_array($this->access_level, ['manager', 'hr_readonly', 'hr_full']);
    }

    public function hasHrReadonlyAccess()
    {
        return in_array($this->access_level, ['hr_readonly', 'hr_full']);
    }

    public function hasHrFullAccess()
    {
        return $this->access_level === 'hr_full';
    }
}