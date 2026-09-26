<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Adds the granular users.* and roles.* action permissions that back the Access Control
     * matrix, and keeps the full-access roles in sync so no admin is locked out.
     */
    public function up(): void
    {
        $names = [];
        foreach (['users', 'roles'] as $area) {
            foreach (['view', 'create', 'edit', 'delete'] as $ability) {
                $names[] = $area.'.'.$ability;
            }
        }

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // super-admin and admin are full-access roles by design. Use givePermissionTo (additive),
        // never syncPermissions, or this would wipe every other permission they already hold.
        $permissions = Permission::whereIn('name', $names)->get();
        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};