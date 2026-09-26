<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['date_of_birth' => 'date', 'permissions' => 'array']);
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
