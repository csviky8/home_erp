<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'decimal:2', 'is_active' => 'boolean']);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
