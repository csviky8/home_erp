<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends HouseholdModel
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_completed' => 'boolean', 'completed_at' => 'datetime']);
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
