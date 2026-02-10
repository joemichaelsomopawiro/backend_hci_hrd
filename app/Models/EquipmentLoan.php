<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_produksi_work_id',
        'borrower_id',
        'approver_id',
        'status',
        'loan_date',
        'return_date',
        'request_notes',
        'approval_notes',
        'return_notes',
    ];

    protected $casts = [
        'loan_date' => 'datetime',
        'return_date' => 'datetime',
    ];

    public function loanItems()
    {
        return $this->hasMany(EquipmentLoanItem::class);
    }

    public function produksiWork()
    {
        return $this->belongsTo(PrProduksiWork::class, 'pr_produksi_work_id');
    }

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function history()
    {
        return $this->hasMany(EquipmentLoanHistory::class, 'equipment_loan_id');
    }

    /**
     * Mark equipment as borrowed (tick action)
     * Updates status to 'active', sets loan_date, decreases inventory, creates history
     */
    public function markAsBorrowed(int $userId, ?string $description = null): void
    {
        \DB::transaction(function () use ($userId, $description) {
            // Update loan status
            $this->update([
                'status' => 'active',
                'loan_date' => now(),
            ]);

            // Decrease inventory quantities
            foreach ($this->loanItems as $loanItem) {
                $inventory = $loanItem->inventoryItem;
                $inventory->decrement('available_quantity', $loanItem->quantity);
            }

            // Create history entry
            EquipmentLoanHistory::logBorrow($this->id, $userId, $description);
        });
    }

    /**
     * Mark equipment as returned (untick action)
     * Updates status to 'returned', sets return_date, increases inventory, creates history
     */
    public function markAsReturned(int $userId, ?string $notes = null): void
    {
        \DB::transaction(function () use ($userId, $notes) {
            // Update loan status
            $this->update([
                'status' => 'returned',
                'return_date' => now(),
                'return_notes' => $notes,
            ]);

            // Restore inventory quantities
            foreach ($this->loanItems as $loanItem) {
                $inventory = $loanItem->inventoryItem;
                $inventory->increment('available_quantity', $loanItem->quantity);
            }

            // Create history entry
            EquipmentLoanHistory::logReturn($this->id, $userId, $notes);
        });
    }
}

