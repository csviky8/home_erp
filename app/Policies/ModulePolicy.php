<?php

namespace App\Policies;

use App\Models\User;
use App\Models\HouseholdModel;

class ModulePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('dashboard.view');
    }

    public function view(User $user, HouseholdModel $record): bool
    {
        return $record->canAccess($user) && $user->can($record->permissionPrefix().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can('records.create');
    }

    public function update(User $user, HouseholdModel $record): bool
    {
        return $record->canAccess($user) && $user->can($record->permissionPrefix().'.edit');
    }

    public function delete(User $user, HouseholdModel $record): bool
    {
        return $record->canAccess($user) && $user->can($record->permissionPrefix().'.delete');
    }
}
