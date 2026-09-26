<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['expense_date' => 'date', 'recurrence_until' => 'date', 'amount' => 'decimal:2']);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
