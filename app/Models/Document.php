<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['expiry_date' => 'date', 'reminder_date' => 'date', 'is_confidential' => 'boolean']);
    }

    public function documentables(): HasMany
    {
        return $this->hasMany(Documentable::class);
    }
}
