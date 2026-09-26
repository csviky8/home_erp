<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'calendar'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_all_day' => 'boolean', 'recurrence_rules' => 'array']);
    }
}
