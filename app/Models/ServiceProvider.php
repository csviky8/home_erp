<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProvider extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'providers'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['rating' => 'decimal:1']);
    }
}
