<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'inventory'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['expiry_date' => 'date', 'quantity' => 'decimal:2', 'minimum_quantity' => 'decimal:2', 'purchase_price' => 'decimal:2']);
    }

    public function movements(): HasMany { return $this->hasMany(InventoryMovement::class); }
}
