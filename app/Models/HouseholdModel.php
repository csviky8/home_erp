<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

abstract class HouseholdModel extends Model
{
    protected $guarded = ['id'];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        return $query->where($query->qualifyColumn('household_id'), $user->household_id);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function canAccess(User $user): bool
    {
        return $user->hasRole('super-admin') || (int) $this->household_id === (int) $user->household_id;
    }

    public function permissionPrefix(): string
    {
        return Str::of(class_basename(static::class))->snake()->plural()->toString();
    }

    public function searchableColumns(): array
    {
        return ['name', 'title', 'description', 'notes'];
    }

    public function fillFromValidated(array $data): static
    {
        $data = collect($data)->except(['id', 'household_id', 'created_at', 'updated_at'])->all();

        $this->fill($data);

        return $this;
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
