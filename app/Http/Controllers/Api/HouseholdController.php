<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class HouseholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(AccessMap::allows($user, 'households', 'view'), 403);
        // Only a super admin sees every family workspace; everyone else sees just their own.
        $households = Household::query()
            ->when(! AccessMap::managesAllHouseholds($user), fn ($query) => $query->whereKey($user->household_id))
            ->orderBy('name')->get(['id', 'name', 'slug', 'timezone', 'currency', 'address', 'created_at'])
            ->loadCount(['users', 'properties']);

        return response()->json(['data' => $households]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize($request);
        abort_unless(AccessMap::managesAllHouseholds($request->user()), 403, 'Only a super admin can create an additional family workspace.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'timezone' => ['required', 'timezone'], 'currency' => ['required', 'string', 'size:3'], 'address' => ['nullable', 'string', 'max:500']]);
        $household = Household::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
        $this->log($request, $household, 'household.created');

        return response()->json(['message' => 'Family created.', 'household' => $household], 201);
    }

    public function update(Request $request, Household $household): JsonResponse
    {
        $this->authorize($request);
        $this->authorizeHousehold($request, $household);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'timezone' => ['sometimes', 'required', 'timezone'], 'currency' => ['sometimes', 'required', 'string', 'size:3'], 'address' => ['nullable', 'string', 'max:500']]);
        $household->update($data);
        $this->log($request, $household, 'household.updated');

        return response()->json(['message' => 'Family updated.', 'household' => $household->fresh()]);
    }

    public function users(Request $request, Household $household): JsonResponse
    {
        $this->authorize($request);
        $this->authorizeHousehold($request, $household);
        $users = $household->users()->with('roles:id,name')->get(['id', 'name', 'email', 'phone', 'is_active', 'last_login_at', 'household_id']);
        return response()->json(['data' => $users]);
    }

    public function storeUser(Request $request, Household $household): JsonResponse
    {
        $this->authorize($request);
        $this->authorizeHousehold($request, $household);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:25'], 'password' => ['required', 'string', 'min:8'], 'role' => ['required', 'string', 'exists:roles,name'], 'relationship' => ['nullable', 'string', 'max:60'], 'is_active' => ['sometimes', 'boolean']]);
        abort_if($data['role'] === 'super-admin' && ! $request->user()->hasRole('super-admin'), 403, 'Only a super admin can grant the super-admin role.');
        $user = User::create(['household_id' => $household->id, 'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'is_active' => $data['is_active'] ?? true]);
        $user->syncRoles([$data['role']]);
        FamilyMember::firstOrCreate(['user_id' => $user->id], ['household_id' => $household->id, 'name' => $data['name'], 'email' => $user->email, 'mobile' => $user->phone, 'relationship' => $data['relationship'] ?? 'Family member']);
        $this->log($request, $household, 'user.created', ['role' => $data['role']]);
        return response()->json(['message' => 'Family member added.', 'user' => $user->load('roles:id,name')], 201);
    }

    private function authorize(Request $request): void
    {
        abort_unless(AccessMap::allows($request->user(), 'households', 'manage'), 403, 'You cannot manage families.');
    }

    /** A non super admin may only read or change their own family workspace. */
    private function authorizeHousehold(Request $request, Household $household): void
    {
        abort_unless(AccessMap::reachesHousehold($request->user(), $household), 403, 'You can only manage your own family.');
    }

    private function log(Request $request, Household $household, string $action, array $metadata = []): void
    {
        ActivityLog::create(['household_id' => $household->id, 'user_id' => $request->user()->id, 'action' => $action, 'module' => 'households', 'record_type' => Household::class, 'record_id' => $household->id, 'metadata' => $metadata, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
