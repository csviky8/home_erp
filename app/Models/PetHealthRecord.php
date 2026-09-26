<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetHealthRecord extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['record_date' => 'date', 'next_due_date' => 'date', 'cost' => 'decimal:2']);
    }

    public function pet(): BelongsTo { return $this->belongsTo(Pet::class); }
}
