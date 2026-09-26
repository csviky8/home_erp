<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends HouseholdModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array', 'created_at' => 'datetime']);
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
