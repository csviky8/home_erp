<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenExpense extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['expense_date' => 'date', 'amount' => 'decimal:2']);
    }

    public function plant(): BelongsTo { return $this->belongsTo(Plant::class); }
}
