<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends HouseholdModel
{
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
