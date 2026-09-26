<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleExpense extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['expense_date' => 'date', 'amount' => 'decimal:2']);
    }

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
