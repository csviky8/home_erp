<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['paid_at' => 'date', 'amount' => 'decimal:2']);
    }

    public function bill(): BelongsTo { return $this->belongsTo(Bill::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
