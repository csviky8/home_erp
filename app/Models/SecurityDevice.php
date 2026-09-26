<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityDevice extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'security'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['installed_on' => 'date', 'next_maintenance_on' => 'date']);
    }

    public function maintenances(): HasMany { return $this->hasMany(SecurityMaintenance::class); }
}
