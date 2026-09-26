<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Household;
use App\Models\Pet;
use App\Models\Plant;
use App\Models\Property;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vehicle;

class LookupService
{
    public function many(User $user, array $sources): array
    {
        $result = [];
        foreach (array_unique($sources) as $source) {
            [$resource, $type] = array_pad(explode('/', $source, 2), 2, null);
            $result[$source] = $this->get($user, $resource, $type);
        }
        return $result;
    }

    public function get(User $user, string $resource, ?string $type = null): array
    {
        $query = match ($resource) {
            'households' => Household::query()->when($user->isSuperAdmin(), fn ($q) => $q->orderBy('id'), fn ($q) => $q->whereKey($user->workingHouseholdId())),
            'categories' => Category::query()->forUser($user)->when($type, fn ($q) => $q->where('type', $type)),
            'properties' => Property::query()->forUser($user),
            'providers' => ServiceProvider::query()->forUser($user),
            'assets' => Asset::query()->forUser($user),
            'vehicles' => Vehicle::query()->forUser($user),
            'pets' => Pet::query()->forUser($user),
            'plants' => Plant::query()->forUser($user),
            'users' => User::query()->when($user->isSuperAdmin(), fn ($q) => $q->orderBy('id'), fn ($q) => $q->where('household_id', $user->workingHouseholdId())),
            default => abort(404, 'Lookup resource not found.'),
        };
        $label = match ($resource) {
            'vehicles' => 'vehicle_number', 'users' => 'name', default => 'name',
        };
        return $query->orderBy($label)->limit(100)->get(['id', $label])->map(fn ($item) => ['id' => $item->id, 'label' => $item->{$label}])->values()->all();
    }
}
