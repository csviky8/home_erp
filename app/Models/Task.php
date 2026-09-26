<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends HouseholdModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['due_at' => 'datetime', 'completed_at' => 'datetime', 'recurrence_rules' => 'array']);
    }

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function checklist(): HasMany { return $this->hasMany(TaskChecklistItem::class); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class); }
    public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class); }
}
