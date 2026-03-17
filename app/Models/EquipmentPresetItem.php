<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentPresetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_preset_id',
        'inventory_item_id',
        'quantity',
    ];

    public function preset()
    {
        return $this->belongsTo(EquipmentPreset::class, 'equipment_preset_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
