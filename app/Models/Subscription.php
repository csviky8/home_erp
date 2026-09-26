<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['start_date' => 'date', 'renewal_date' => 'date', 'amount' => 'decimal:2']);
    }

    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
}
