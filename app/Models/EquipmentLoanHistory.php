<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentLoanHistory extends Model
{
    use HasFactory;

    protected $table = 'equipment_loan_history';

    protected $fillable = [
        'equipment_loan_id',
        'action',
        'action_date',
        'performed_by',
        'description',
    ];

    protected $casts = [
        'action_date' => 'datetime',
    ];

    // Relationships
    public function loan()
    {
        return $this->belongsTo(EquipmentLoan::class, 'equipment_loan_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Create a history entry for borrowed action
     */
    public static function logBorrow(int $loanId, int $userId, ?string $description = null): self
    {
        return self::create([
            'equipment_loan_id' => $loanId,
            'action' => 'borrowed',
            'action_date' => now(),
            'performed_by' => $userId,
            'description' => $description,
        ]);
    }

    /**
     * Create a history entry for returned action
     */
    public static function logReturn(int $loanId, int $userId, ?string $description = null): self
    {
        return self::create([
            'equipment_loan_id' => $loanId,
            'action' => 'returned',
            'action_date' => now(),
            'performed_by' => $userId,
            'description' => $description,
        ]);
    }
}
