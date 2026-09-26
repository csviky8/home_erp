<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Documentable extends HouseholdModel
{
    protected $table = 'documentables';

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function documentable(): MorphTo { return $this->morphTo(); }
}
