<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['data' => 'array', 'read_at' => 'datetime']);
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
