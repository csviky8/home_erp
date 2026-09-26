<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['visited_at' => 'datetime', 'checked_out_at' => 'datetime']);
    }
}
