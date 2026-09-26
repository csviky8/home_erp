<?php

namespace App\Support;

use App\Models\Household;
use App\Models\User;

/**
 * Single source of truth for "which permission unlocks which area of the app".
 *
 * Every backend guard and the Vue interface read this map, so a panel can never be
 * hidden in the UI while the API allows it (or the other way around).
 */
class AccessMap
{
    /**
     * @var array<string, array{label: string, group: string, permissions: array<string, string>}>
     */
    public const AREAS = [
        'dashboard' => ['label' => 'Dashboard', 'group' => 'Overview', 'permissions' => ['view' => 'dashboard.view']],
        'settings' => ['label' => 'Settings', 'group' => 'Administration', 'permissions' => ['view' => 'settings.view', 'manage' => 'settings.manage']],
        'users' => ['label' => 'Users', 'group' => 'Administration', 'permissions' => [
            'view' => 'users.view', 'create' => 'users.create', 'edit' => 'users.edit', 'delete' => 'users.delete', 'manage' => 'settings.manage',
        ]],
        'roles' => ['label' => 'Role manager', 'group' => 'Administration', 'permissions' => [
            'view' => 'roles.view', 'create' => 'roles.create', 'edit' => 'roles.edit', 'delete' => 'roles.delete',
        ]],
        'households' => ['label' => 'Families', 'group' => 'Administration', 'permissions' => ['view' => 'settings.manage', 'manage' => 'settings.manage']],
        'reports' => ['label' => 'Reports & exports', 'group' => 'Intelligence', 'permissions' => ['view' => 'reports.view', 'export' => 'reports.export']],
        'ai' => ['label' => 'AI assistant', 'group' => 'Intelligence', 'permissions' => ['use' => 'ai.use']],
    ];

    /**
     * Umbrella permission: `settings.manage` grants every administrative action, so an admin
     * keeps full control even before the granular permissions below are assigned.
     */
    public const UMBRELLA = 'settings.manage';

    /** Roles shipped with the system; they cannot be deleted and only a super admin may rewrite them. */
    public const SYSTEM_ROLES = ['super-admin', 'admin', 'family-member', 'staff'];

    public static function allows(?User $user, string $area, string $ability = 'view'): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super-admin')) {
            return true;
        }
        $permission = self::permissionFor($area, $ability);
        if ($permission === null) {
            return false;
        }
        if ($user->can($permission)) {
            return true;
        }

        // The umbrella grants every administrative action except its own settings.manage entry.
        return $permission !== self::UMBRELLA && $user->can(self::UMBRELLA);
    }

    /**
     * Data isolation. Permissions decide *what* you may do; this decides *whose data*
     * you may touch. A super admin may work across families, everyone else is locked to
     * their own household no matter how broad their permissions are.
     */
    public static function reachesHousehold(?User $user, Household|int|null $household): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super-admin')) {
            return true;
        }
        $id = $household instanceof Household ? $household->id : $household;

        return $id !== null && (int) $user->household_id === (int) $id;
    }

    /** Only a super admin may create or enumerate additional family workspaces. */
    public static function managesAllHouseholds(?User $user): bool
    {
        return (bool) $user?->hasRole('super-admin');
    }

    public static function permissionFor(string $area, string $ability = 'view'): ?string
    {
        return self::AREAS[$area]['permissions'][$ability] ?? null;
    }

    /**
     * The area map with the current user's abilities resolved, so the UI never
     * has to guess what a role is allowed to do.
     *
     * @return array<string, array<string, bool>>
     */
    public static function resolvedFor(?User $user): array
    {
        $resolved = [];
        foreach (self::AREAS as $area => $definition) {
            foreach (array_keys($definition['permissions']) as $ability) {
                $resolved[$area][$ability] = self::allows($user, $area, $ability);
            }
        }

        return $resolved;
    }
}
