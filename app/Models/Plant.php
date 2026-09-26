<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'garden'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['planted_at' => 'date', 'last_watered_at' => 'date', 'next_watering_at' => 'date']);
    }

    public function expenses(): HasMany { return $this->hasMany(GardenExpense::class); }
    public function gardener(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
}
