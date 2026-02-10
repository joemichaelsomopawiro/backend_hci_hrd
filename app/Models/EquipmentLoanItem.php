<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentLoanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_loan_id',
        'inventory_item_id',
        'quantity',
    ];

    public function loan()
    {
        return $this->belongsTo(EquipmentLoan::class, 'equipment_loan_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
