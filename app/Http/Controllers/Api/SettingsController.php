<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->household && ! $user->hasRole('super-admin'), 403, 'No household is assigned.');
        $household = $user->household ?? Household::first();
        abort_unless(AccessMap::allows($user, 'settings', 'view'), 403);

        return response()->json(['household' => $household, 'categories' => Category::query()->forUser($user)->orderBy('type')->orderBy('name')->get(), 'roles' => AccessMap::allows($user, 'settings', 'manage') ? Role::where('guard_name', 'web')->get(['id', 'name']) : []]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AccessMap::allows($user, 'settings', 'manage'), 403);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'timezone' => ['sometimes', 'required', 'timezone'], 'currency' => ['sometimes', 'required', 'string', 'size:3'], 'address' => ['nullable', 'string'], 'settings' => ['sometimes', 'array']]);
        $household = $user->household ?? Household::first();
        $household->update($data);
        $this->log($request, $user, 'settings.updated', 'settings', null);

        return response()->json(['message' => 'Settings saved.', 'household' => $household->fresh()]);
    }

    public function users(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AccessMap::allows($user, 'users', 'view'), 403);
        $users = User::query()->when(! $user->hasRole('super-admin'), fn ($q) => $q->where('household_id', $user->household_id))->with('roles:id,name')->get(['id', 'household_id', 'name', 'email', 'phone', 'is_active', 'last_login_at']);

        return response()->json(['data' => $users]);
    }
    public function access(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'view');
        $roles = Role::where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get(['id', 'name'])->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->values()]);
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'group' => str($permission->name)->before('.')->toString()]);

        return response()->json(['roles' => $roles, 'permissions' => $permissions, 'system_roles' => AccessMap::SYSTEM_ROLES, 'areas' => AccessMap::AREAS, 'abilities' => AccessMap::resolvedFor($request->user())]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'create');
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:roles,name'], 'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, $request->user(), 'role.created', 'roles', null, ['role' => $role->name]);

        return response()->json(['message' => 'Role created.', 'role' => $role->load('permissions:id,name')], 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->authorizeAccess($request, 'edit');
        abort_if($role->name === 'super-admin', 422, 'The super-admin role cannot be changed.');
        $this->authorizeSystemRole($request, $role);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->ignore($role->id)], 'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        if (isset($data['name'])) $role->update(['name' => $data['name']]);
        if (array_key_exists('permissions', $data)) $role->syncPermissions($data['permissions']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, $request->user(), 'role.updated', 'roles', null, ['role' => $role->name]);

        return response()->json(['message' => 'Role updated.', 'role' => $role->fresh('permissions:id,name')]);
    }

    public function destroyRole(Request $request, Role $role): JsonResponse
    {
        $this->authorizeAccess($request, 'delete');
        abort_if(in_array($role->name, ['super-admin', 'admin', 'family-member', 'staff'], true), 422, 'System roles cannot be deleted.');
        $role->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, $request->user(), 'role.deleted', 'roles', null, ['role' => $role->name]);

        return response()->json(['message' => 'Role deleted.']);
    }

    public function storeUser(Request $request): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'users', 'create'), 403, 'You cannot create users.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:25'], 'password' => ['required', 'string', 'min:8'], 'role' => ['required', 'string', 'exists:roles,name'], 'is_active' => ['sometimes', 'boolean'], 'household_id' => ['nullable', 'integer', 'exists:households,id']]);
        $householdId = $request->user()->hasRole('super-admin') ? ($data['household_id'] ?? $request->user()->household_id) : $request->user()->household_id;
        $this->authorizeRoleAssignment($request, $data['role']);
        abort_if(! $householdId, 422, 'A household is required.');
        $user = User::create(['household_id' => $householdId, 'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'is_active' => $data['is_active'] ?? true]);
        $user->syncRoles([$data['role']]);
        FamilyMember::firstOrCreate(['user_id' => $user->id], ['household_id' => $householdId, 'name' => $data['name'], 'email' => $user->email, 'mobile' => $user->phone, 'relationship' => 'Family member']);
        $this->log($request, $request->user(), 'user.created', 'users', $user->id, ['role' => $data['role']]);

        return response()->json(['message' => 'User created.', 'user' => $user->load('roles:id,name')], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless(AccessMap::allows($actor, 'users', 'edit'), 403, 'You cannot edit users.');
        $this->authorizeTargetUser($request, $user);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)], 'phone' => ['nullable', 'string', 'max:25'], 'password' => ['nullable', 'string', 'min:8'], 'role' => ['sometimes', 'string', 'exists:roles,name'], 'is_active' => ['sometimes', 'boolean']]);
        if (isset($data['role'])) $this->authorizeRoleAssignment($request, $data['role']);
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'is_active'])->all());
        if (! empty($data['password'])) $user->password = $data['password'];
        $user->save();
        if (isset($data['role'])) $user->syncRoles([$data['role']]);
        $this->log($request, $actor, 'user.updated', 'users', $user->id, array_filter(['role' => $data['role'] ?? null]));

        return response()->json(['message' => 'User updated.', 'user' => $user->fresh('roles:id,name')]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless(AccessMap::allows($actor, 'users', 'delete'), 403, 'You cannot delete users.');
        $this->authorizeTargetUser($request, $user);
        abort_if($user->id === $actor->id, 422, 'You cannot delete your own account.');
        abort_if($user->hasRole('super-admin'), 422, 'The super-admin account cannot be deleted.');
        $user->delete();
        $this->log($request, $actor, 'user.deleted', 'users', $user->id);

        return response()->json(['message' => 'User deleted.']);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless(AccessMap::allows($actor, 'users', 'edit'), 403, 'You cannot change a user role.');
        $this->authorizeTargetUser($request, $user);
        $data = $request->validate(['role' => ['required', 'string', Rule::exists('roles', 'name')]]);
        $this->authorizeRoleAssignment($request, $data['role']);
        $role = Role::where('name', $data['role'])->where('guard_name', 'web')->firstOrFail();
        $user->syncRoles($role);
        $this->log($request, $actor, 'permission.changed', 'users', $user->id, ['role' => $role->name]);

        return response()->json(['message' => 'Role updated.', 'user' => $user->fresh()->load('roles:id,name')]);
    }

    /**
     * Full permission setup for a single user: the permission catalogue grouped by area plus
     * the permissions the user currently resolves to through their role.
     */
    public function userPermissions(Request $request, User $user): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'users', 'view'), 403, 'You cannot view users.');
        $this->authorizeTargetUser($request, $user);
        $role = $user->roles()->first();
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'group' => str($permission->name)->before('.')->toString()]);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => (bool) $user->is_active],
            'role' => $role ? ['id' => $role->id, 'name' => $role->name, 'system' => in_array($role->name, AccessMap::SYSTEM_ROLES, true)] : null,
            'permissions' => $permissions,
            'assigned' => $user->getAllPermissions()->pluck('name')->values(),
            'editable' => ! $user->hasRole('super-admin'),
        ]);
    }

    /**
     * Apply a permission set to one user. The user keeps exactly one role, so saving creates or
     * reuses a dedicated role for them rather than mutating a role shared by other people.
     */
    public function updateUserPermissions(Request $request, User $user): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'roles', 'edit'), 403, 'You cannot change permissions.');
        $this->authorizeTargetUser($request, $user);
        abort_if($user->hasRole('super-admin'), 422, 'The super admin account always has full access.');
        $data = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        $names = array_values(array_unique($data['permissions']));

        $role = $user->roles()->first();
        // Reuse a personal role, otherwise make one. Never touch a role shared with other users.
        $personal = $role && ! in_array($role->name, AccessMap::SYSTEM_ROLES, true) && str_starts_with($role->name, 'user-');
        if (! $personal) {
            $role = Role::firstOrCreate(['name' => 'user-'.$user->id, 'guard_name' => 'web']);
            $user->syncRoles([$role->name]);
        }
        $role->syncPermissions($names);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, $request->user(), 'permission.changed', 'users', $user->id, ['role' => $role->name, 'count' => count($names)]);

        return response()->json(['message' => 'Permissions updated for '.$user->name.'.', 'user' => $user->fresh('roles:id,name'), 'assigned' => $user->fresh()->getAllPermissions()->pluck('name')->values()]);
    }

    public function saveCategory(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AccessMap::allows($user, 'settings', 'manage'), 403);
        $data = $request->validate(['id' => ['nullable', 'integer'], 'type' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:80'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'icon' => ['nullable', 'string', 'max:40'], 'is_active' => ['sometimes', 'boolean']]);
        $category = Category::query()->forUser($user)->when($data['id'] ?? null, fn ($q) => $q->whereKey($data['id']))->first();
        if (! $category) $category = new Category(['household_id' => $user->household_id]);
        $category->fill($data)->save();
        $this->log($request, $user, $category->wasRecentlyCreated ? 'category.created' : 'category.updated', 'categories', $category->id);

        return response()->json(['category' => $category], $category->wasRecentlyCreated ? 201 : 200);
    }

    /** Role administration is a separate area, so it can be granted on its own. */
    private function authorizeAccess(Request $request, string $ability = 'view'): void
    {
        abort_unless(AccessMap::allows($request->user(), 'roles', $ability), 403, 'You cannot manage users and roles.');
    }

    /**
     * Only a super admin may rewrite a system role, otherwise an admin could strip
     * its own management permissions (locking everyone out) or hand every permission
     * to family-member.
     */
    private function authorizeSystemRole(Request $request, Role $role): void
    {
        if (in_array($role->name, AccessMap::SYSTEM_ROLES, true) && ! $request->user()->hasRole('super-admin')) {
            abort(422, 'Only a super admin can change the permissions of a system role.');
        }
    }

    /** Only a super admin may hand out the super-admin role. */
    private function authorizeRoleAssignment(Request $request, ?string $role): void
    {
        if ($role === 'super-admin' && ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Only a super admin can grant the super-admin role.');
        }
    }

    /**
     * Data isolation for user administration: holding settings.manage lets you administer
     * your own family, not somebody else's.
     */
    private function authorizeTargetUser(Request $request, User $user): void
    {
        abort_unless(AccessMap::reachesHousehold($request->user(), $user->household_id), 403, 'You can only manage users in your own family.');
    }

    private function log(Request $request, User $actor, string $action, string $module, ?int $recordId, array $metadata = []): void
    {
        ActivityLog::create(['household_id' => $actor->household_id, 'user_id' => $actor->id, 'action' => $action, 'module' => $module, 'record_type' => null, 'record_id' => $recordId, 'metadata' => $metadata, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
