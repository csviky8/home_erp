<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['purchase_date' => 'date', 'next_service_date' => 'date', 'puc_expiry' => 'date', 'rc_expiry' => 'date', 'purchase_price' => 'decimal:2']);
    }

    public function services(): HasMany { return $this->hasMany(VehicleService::class); }
    public function expenses(): HasMany { return $this->hasMany(VehicleExpense::class); }
    public function insurancePolicies(): HasMany { return $this->hasMany(InsurancePolicy::class); }
}
