<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['movement_date' => 'date', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2']);
    }

    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
}
