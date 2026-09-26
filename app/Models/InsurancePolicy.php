<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsurancePolicy extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'insurance'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['start_date' => 'date', 'expiry_date' => 'date', 'renewal_date' => 'date', 'premium' => 'decimal:2', 'coverage_amount' => 'decimal:2']);
    }

    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
}
