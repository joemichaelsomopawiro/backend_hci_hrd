<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrTalent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pr_talents';

    protected $fillable = [
        'name',
        'title',
        'birth_place_date',
        'expertise',
        'type'
    ];
}
