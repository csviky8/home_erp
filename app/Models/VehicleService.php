<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleService extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['service_date' => 'date', 'next_service_date' => 'date', 'cost' => 'decimal:2']);
    }

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
}
