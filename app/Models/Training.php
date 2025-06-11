<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'training_name',
        'institution',
        'completion_date',
        'certificate_number',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}