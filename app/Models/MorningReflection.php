<?php
    namespace App\Models;
    use Illuminate\Database\Eloquent\Model;

    class MorningReflection extends Model
{
    protected $fillable = ['employee_id', 'date', 'status', 'join_time'];
    
    protected $casts = [
        'date' => 'date',
        'join_time' => 'datetime'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}