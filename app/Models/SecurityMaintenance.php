<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityMaintenance extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['service_date' => 'date', 'cost' => 'decimal:2']);
    }

    public function device(): BelongsTo { return $this->belongsTo(SecurityDevice::class, 'security_device_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
}
