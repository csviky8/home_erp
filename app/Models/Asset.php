<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['purchase_date' => 'date', 'warranty_expiry' => 'date', 'purchase_price' => 'decimal:2']);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function maintenanceRequests(): HasMany { return $this->hasMany(MaintenanceRequest::class); }
    public function insurancePolicies(): HasMany { return $this->hasMany(InsurancePolicy::class); }
}
