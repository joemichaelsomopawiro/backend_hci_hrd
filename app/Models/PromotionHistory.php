<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'position',
        'promotion_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}