<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['due_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2']);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
