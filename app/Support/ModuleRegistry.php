<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ModuleRegistry
{
    public function all(): array
    {
        return config('home_modules', []);
    }

    public function get(string $key): ?array
    {
        return Arr::get($this->all(), $key);
    }

    public function model(string $key): string
    {
        $module = $this->get($key);

        abort_if($module === null, 404, 'Module not found.');

        return $module['model'];
    }

    public function can(User $user, array $module, string $ability): bool
    {
        return $user->hasRole('super-admin') || $user->can(($module['prefix'] ?? 'unknown').'.'.$ability);
    }

    public function relations(string $key): array
    {
        return [
            'expenses' => ['category', 'property', 'user'],
            'bills' => ['category', 'property', 'provider', 'payments'],
            'maintenance' => ['asset', 'provider', 'assignee'],
            'assets' => ['property'],
            'tasks' => ['assignee', 'creator'],
            'budgets' => ['category', 'property'],
            'insurance' => ['provider', 'property'],
            'garden' => ['gardener'],
            'calendar' => ['user'],
        ][$key] ?? [];
    }

    public function findVisible(string $key, int|string $id, User $user): Model
    {
        $model = $this->model($key);
        $record = $model::query()->forUser($user)->with($this->relations($key))->findOrFail($id);
        abort_unless($record->canAccess($user), 403, 'You are not allowed to access this household record.');

        return $record;
    }
}
