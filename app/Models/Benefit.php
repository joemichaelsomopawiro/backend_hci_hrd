<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Benefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'benefit_type',
        'amount',
        'start_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}