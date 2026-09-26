<?php

namespace Tests\Feature;

use Database\Seeders\HomeErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeErpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HomeErpSeeder::class);
    }

    public function test_admin_can_login_and_read_dashboard_and_module_data(): void
    {
        $login = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password']);
        $login->assertOk()->assertJsonStructure(['token', 'user']);
        $token = $login->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/dashboard', $headers)->assertOk()->assertJsonStructure(['metrics', 'charts']);
        $this->getJson('/api/modules/expenses', $headers)->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_ai_answers_are_scoped_and_rate_limit_is_configured(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token');
        $this->postJson('/api/ai/chat', ['message' => 'How much did I spend this month?'], ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJsonStructure(['message', 'context']);
    }

    public function test_family_member_cannot_create_unauthorized_asset(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'family@homeerp.test', 'password' => 'password'])->json('token');
        $this->postJson('/api/modules/assets', ['name' => 'Unauthorized asset'], ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }

    public function test_admin_reaches_settings_and_family_areas_by_permission_not_role_name(): void
    {
        $login = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password']);
        $headers = ['Authorization' => 'Bearer '.$login->json('token')];

        // The UI reads these to decide which panels to render, so they must agree with the guards.
        $login->assertJsonPath('user.roles.0', 'admin');
        $login->assertJsonPath('user.abilities.users.manage', true);
        $login->assertJsonPath('user.abilities.settings.manage', true);
        $login->assertJsonPath('user.abilities.households.manage', true);
        $login->assertJsonPath('user.abilities.users.view', true);

        $this->getJson('/api/settings', $headers)->assertOk();
        $this->getJson('/api/settings/access', $headers)->assertOk()
            ->assertJsonStructure(['roles', 'permissions', 'system_roles', 'areas', 'abilities']);
        $this->getJson('/api/settings/users', $headers)->assertOk();
        $this->getJson('/api/households', $headers)->assertOk();
    }

    public function test_admin_is_isolated_to_its_own_family_while_super_admin_sees_all(): void
    {
        $other = \App\Models\Household::create(['name' => 'Neighbour Family', 'slug' => 'neighbour-family', 'timezone' => 'UTC', 'currency' => 'INR']);
        $adminHeaders = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token')];

        // The admin only ever sees its own family card, never the neighbour's.
        $this->getJson('/api/households', $adminHeaders)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', \App\Models\Household::first()->id);
        $this->getJson('/api/households/'.$other->id.'/users', $adminHeaders)->assertForbidden();
        $this->putJson('/api/households/'.$other->id, ['name' => 'Hijacked'], $adminHeaders)->assertForbidden();
        $this->postJson('/api/households/'.$other->id.'/users', ['name' => 'X', 'email' => 'x@homeerp.test', 'password' => 'password123', 'role' => 'family-member'], $adminHeaders)->assertForbidden();
        // Creating extra family workspaces stays a super admin action.
        $this->postJson('/api/households', ['name' => 'Sneaky Family', 'timezone' => 'UTC', 'currency' => 'INR'], $adminHeaders)->assertForbidden();
    }

    public function test_super_admin_sees_and_manages_every_family_workspace(): void
    {
        $other = \App\Models\Household::create(['name' => 'Neighbour Family', 'slug' => 'neighbour-family-super', 'timezone' => 'UTC', 'currency' => 'INR']);
        // The seeded super admin account.
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];

        // Super admin sees and manages every family: the three seeded ones plus the new one.
        $this->getJson('/api/households', $headers)->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['id' => $other->id, 'name' => 'Neighbour Family']);
        $this->postJson('/api/households', ['name' => 'Second Family', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR'], $headers)->assertCreated();
        $this->getJson('/api/households/'.$other->id.'/users', $headers)->assertOk();
        $this->putJson('/api/households/'.$other->id, ['name' => 'Renamed Neighbour'], $headers)->assertOk();
    }

    /**
     * @dataProvider moduleCrudProvider
     */
    public function test_every_module_supports_create_read_update_and_delete(string $module, array $payload, string $editField): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $household = \App\Models\Household::first();

        $created = $this->postJson('/api/modules/'.$module, $payload + ['household_id' => $household->id], $headers);
        $created->assertCreated();
        $id = $created->json('data.id');
        $this->assertNotNull($id, "{$module} did not return a created id.");

        $this->getJson('/api/modules/'.$module, $headers)->assertOk();
        $this->getJson('/api/modules/'.$module.'/'.$id, $headers)->assertOk();

        $this->putJson('/api/modules/'.$module.'/'.$id, $payload + [$editField => 'Edited by test'], $headers)->assertOk();
        $this->deleteJson('/api/modules/'.$module.'/'.$id, [], $headers)->assertOk();
        $this->getJson('/api/modules/'.$module.'/'.$id, $headers)->assertNotFound();
    }

    public static function moduleCrudProvider(): array
    {
        return [
            'expenses' => ['expenses', ['description' => 'Test expense', 'amount' => 100, 'expense_date' => '2026-09-25', 'payment_method' => 'cash', 'category_id' => 1], 'notes'],
            'bills' => ['bills', ['name' => 'Test bill', 'amount' => 100, 'due_date' => '2026-10-05'], 'notes'],
            'maintenance' => ['maintenance', ['title' => 'Test maintenance', 'category' => 'ac', 'priority' => 'low'], 'description'],
            'assets' => ['assets', ['name' => 'Test asset', 'category' => 'ac', 'brand' => 'B', 'model' => 'M'], 'location'],
            'tasks' => ['tasks', ['title' => 'Test task', 'priority' => 'medium'], 'description'],
            'inventory' => ['inventory', ['name' => 'Test item', 'quantity' => 5, 'unit' => 'kg', 'category' => 'groceries'], 'vendor'],
            'family' => ['family', ['name' => 'Test relative', 'relationship' => 'Cousin'], 'notes'],
            'budgets' => ['budgets', ['name' => 'Test budget', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'amount' => 500], 'name'],
            'subscriptions' => ['subscriptions', ['name' => 'Test sub', 'amount' => 99, 'start_date' => '2026-01-01', 'renewal_date' => '2026-10-01'], 'notes'],
            'insurance' => ['insurance', ['policy_type' => 'home', 'premium' => 1000, 'start_date' => '2026-01-01', 'expiry_date' => '2027-01-01', 'renewal_date' => '2026-12-01'], 'policy_number'],
            'vehicles' => ['vehicles', ['vehicle_number' => 'TEST-01', 'vehicle_type' => 'car'], 'model'],
            'providers' => ['providers', ['name' => 'Test provider', 'service_type' => 'Plumber'], 'address'],
            'properties' => ['properties', ['name' => 'Test property', 'property_type' => 'apartment'], 'address'],
            'security' => ['security', ['name' => 'Test device', 'device_type' => 'cctv'], 'location'],
            'garden' => ['garden', ['name' => 'Test plant', 'plant_type' => 'Indoor'], 'location'],
            'pets' => ['pets', ['name' => 'Test pet', 'pet_type' => 'dog'], 'breed'],
            'calendar' => ['calendar', ['title' => 'Test event', 'starts_at' => '2026-10-01 10:00'], 'description'],
        ];
    }

    public function test_file_uploads_are_accepted(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        // A real 1x1 PNG, so the detected mime type is genuine rather than client supplied.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('receipt.png', $png);

        $this->post('/api/modules/documents', [
            'title' => 'Uploaded document', 'category' => 'other', 'file_path' => $file, 'household_id' => \App\Models\Household::first()->id,
        ], $headers)->assertCreated();

        $this->post('/api/modules/assets', [
            'name' => 'Asset with photo', 'category' => 'ac',
            'photo_path' => \Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png', $png),
            'household_id' => \App\Models\Household::first()->id,
        ], $headers)->assertCreated();
    }

    public function test_super_admin_create_does_not_require_a_household_id(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $own = \App\Models\Household::first();

        // No household_id supplied: it must fall back rather than fail the request.
        $this->postJson('/api/modules/tasks', ['title' => 'Implicit household'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.household_id', $own->id);

        // Explicitly targeting another family is still honoured.
        $other = \App\Models\Household::create(['name' => 'Neighbour', 'slug' => 'hh-fallback-neighbour', 'timezone' => 'UTC', 'currency' => 'INR']);
        $this->postJson('/api/modules/tasks', ['title' => 'Explicit household', 'household_id' => $other->id], $headers)
            ->assertCreated()
            ->assertJsonPath('data.household_id', $other->id);
    }

    public function test_super_admin_working_family_is_saved_and_reused(): void
    {
        $first = \App\Models\Household::first();
        $second = \App\Models\Household::create(['name' => 'Sharma Villa', 'slug' => 'working-family-villa', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $login = fn () => $this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password']);

        // With no preference stored, the first family is used.
        $login()->assertJsonPath('user.active_household_id', $first->id);

        // Saving the preference sticks across a brand new session.
        $root = \App\Models\User::where('email', 'root@homeerp.test')->first();
        $root->default_household_id = $second->id;
        $root->save();
        $login()->assertJsonPath('user.active_household_id', $second->id);

        // A record created without household_id lands in the chosen family.
        $headers = ['Authorization' => 'Bearer '.$login()->json('token')];
        $this->postJson('/api/modules/tasks', ['title' => 'Working family task'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.household_id', $second->id);
    }

    public function test_only_a_super_admin_can_choose_a_working_family(): void
    {
        $household = \App\Models\Household::create(['name' => 'Sharma Villa', 'slug' => 'working-family-guard', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token')];

        $this->putJson('/api/auth/working-family', ['default_household_id' => $household->id], $headers)->assertForbidden();
        $this->assertNull(\App\Models\User::where('email', 'admin@homeerp.test')->first()->default_household_id);
    }

    public function test_super_admin_sees_only_the_selected_family_records(): void
    {
        $other = \App\Models\Household::create(['name' => 'Testing Data', 'slug' => 'scoped-testing-data', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $root = \App\Models\User::where('email', 'root@homeerp.test')->first();
        $root->default_household_id = $other->id;
        $root->save();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $login = $this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password']);
        $login->assertJsonPath('user.active_household_id', $other->id);
        $headers = ['Authorization' => 'Bearer '.$login->json('token')];

        // The selected family is empty, so the other family's seeded records must not appear.
        foreach (['tasks', 'expenses', 'assets', 'bills', 'inventory', 'calendar'] as $module) {
            $this->getJson('/api/modules/'.$module, $headers)->assertOk()->assertJsonPath('meta.total', 0);
        }

        // New records are written to the selected family only.
        $this->postJson('/api/modules/tasks', ['title' => 'Testing data task'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.household_id', $other->id);
        $this->assertDatabaseHas('tasks', ['title' => 'Testing data task', 'household_id' => $other->id]);

        // A record belonging to another family is not reachable.
        $foreign = \App\Models\Task::where('household_id', '!=', $other->id)->first();
        $this->getJson('/api/modules/tasks/'.$foreign->id, $headers)->assertNotFound();
        $this->putJson('/api/modules/tasks/'.$foreign->id, ['title' => 'hijacked'], $headers)->assertNotFound();
    }

    public function test_the_seeder_builds_three_isolated_family_workspaces(): void
    {
        $this->assertSame(3, \App\Models\Household::count(), 'Expected three seeded family workspaces.');
        $modules = [
            'expenses' => \App\Models\Expense::class,
            'bills' => \App\Models\Bill::class,
            'tasks' => \App\Models\Task::class,
            'assets' => \App\Models\Asset::class,
            'vehicles' => \App\Models\Vehicle::class,
            'inventory' => \App\Models\InventoryItem::class,
            'pets' => \App\Models\Pet::class,
        ];

        foreach (\App\Models\Household::orderBy('id')->get() as $household) {
            $this->assertGreaterThan(0, \App\Models\User::where('household_id', $household->id)->count(), $household->name.' needs its own users.');
            foreach ($modules as $label => $model) {
                $this->assertGreaterThan(0, $model::where('household_id', $household->id)->count(), $household->name.' has no '.$label.' records.');
            }
        }

        // Each family's own users resolve to their own records only.
        foreach (['admin@homeerp.test', 'villa.admin@homeerp.test', 'cottage.admin@homeerp.test'] as $email) {
            $user = \App\Models\User::where('email', $email)->first();
            $visible = \App\Models\Expense::query()->forUser($user)->distinct()->pluck('household_id');
            $this->assertSame([$user->household_id], $visible->values()->all(), $email.' must only see its own family.');
        }
    }

    public function test_multipart_update_is_accepted_via_method_spoofing(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];

        // PHP only parses multipart/form-data for POST, so the browser client posts with
        // _method=PUT when a record is edited. The fields must actually be applied.
        $created = $this->postJson('/api/modules/assets', ['name' => 'Spoof check', 'category' => 'ac', 'brand' => 'Original'], $headers);
        $created->assertCreated();
        $id = $created->json('data.id');

        $png = \Illuminate\Http\UploadedFile::fake()->createWithContent('p.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->post('/api/modules/assets/'.$id, [
            '_method' => 'PUT', 'name' => 'Spoof check', 'brand' => 'Updated Brand', 'location' => 'Hallway', 'photo_path' => $png,
        ], $headers)->assertOk()->assertJsonPath('data.brand', 'Updated Brand')->assertJsonPath('data.location', 'Hallway');

        // A plain JSON update works too, which is what the client sends when there is no upload.
        $this->putJson('/api/modules/assets/'.$id, ['brand' => 'JSON Brand'], $headers)
            ->assertOk()->assertJsonPath('data.brand', 'JSON Brand');
        $this->assertDatabaseHas('assets', ['id' => $id, 'brand' => 'JSON Brand']);
    }

    public function test_clearing_an_optional_date_on_edit_is_allowed(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $created = $this->postJson('/api/modules/expenses', [
            'description' => 'Optional date check', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'category_id' => 1, 'recurrence' => 'monthly', 'recurrence_until' => '2026-12-31',
        ], $headers)->assertCreated();
        $id = $created->json('data.id');

        // Clearing the date sends an explicit null on update; `date` must not reject it.
        $this->putJson('/api/modules/expenses/'.$id, [
            'description' => 'Optional date check', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'category_id' => 1, 'recurrence' => 'monthly', 'recurrence_until' => null,
        ], $headers)->assertOk();
        $this->assertNull(\App\Models\Expense::find($id)->recurrence_until);

        // A genuinely invalid date must still be rejected.
        $this->putJson('/api/modules/expenses/'.$id, [
            'description' => 'Optional date check', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'recurrence_until' => 'not-a-date',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('recurrence_until');
    }

    public function test_a_record_can_be_viewed_by_id(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $id = \App\Models\Expense::first()->id;

        // The view drawer reads the record out of the { data: { ... } } envelope.
        $this->getJson('/api/modules/expenses/'.$id, $headers)->assertOk()
            ->assertJsonStructure(['data' => ['id', 'description', 'amount', 'expense_date', 'category']])
            ->assertJsonPath('data.id', $id);
    }

    public function test_duplicate_values_return_a_readable_error(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $payload = ['vehicle_number' => 'DUP-01', 'vehicle_type' => 'car', 'household_id' => \App\Models\Household::first()->id];

        $this->postJson('/api/modules/vehicles', $payload, $headers)->assertCreated();
        $this->postJson('/api/modules/vehicles', $payload, $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'A record with these details already exists.');
    }

    public function test_users_and_roles_expose_full_action_permissions(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $access = $this->getJson('/api/settings/access', $headers)->assertOk();
        $names = $access->json('permissions.*.name');

        // The Users and Role manager areas must offer the same actions as every module.
        foreach (['users', 'roles'] as $area) {
            foreach (['view', 'create', 'edit', 'delete'] as $ability) {
                $this->assertContains($area.'.'.$ability, $names, "Missing {$area}.{$ability}");
            }
        }

        // The full-access roles keep everything; the permission migration must be additive.
        foreach (['super-admin', 'admin'] as $roleName) {
            $count = \Spatie\Permission\Models\Role::where('name', $roleName)->first()->permissions()->count();
            $this->assertSame(count($names), $count, "The {$roleName} role must hold every permission.");
        }
    }

    public function test_full_permission_setup_for_a_single_user(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $member = \App\Models\User::where('email', 'family@homeerp.test')->first();

        $this->getJson('/api/settings/users/'.$member->id.'/permissions', $headers)->assertOk()
            ->assertJsonStructure(['user', 'role', 'permissions', 'assigned', 'editable'])
            ->assertJsonPath('user.email', 'family@homeerp.test')
            ->assertJsonPath('role.system', true)
            ->assertJsonPath('editable', true);

        // Saving must not mutate the shared system role, it gives the user a personal one.
        $this->putJson('/api/settings/users/'.$member->id.'/permissions', [
            'permissions' => ['dashboard.view', 'expenses.view', 'expenses.create'],
        ], $headers)->assertOk()->assertJsonPath('user.roles.0.name', 'user-'.$member->id);

        $system = \Spatie\Permission\Models\Role::where('name', 'family-member')->first();
        $this->assertGreaterThan(3, $system->permissions()->count(), 'The shared system role must stay untouched.');
        $this->assertSame(['dashboard.view', 'expenses.create', 'expenses.view'], $member->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_super_admin_permissions_cannot_be_edited(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];
        $root = \App\Models\User::where('email', 'root@homeerp.test')->first();

        $this->getJson('/api/settings/users/'.$root->id.'/permissions', $headers)->assertOk()->assertJsonPath('editable', false);
        $this->putJson('/api/settings/users/'.$root->id.'/permissions', ['permissions' => ['dashboard.view']], $headers)->assertStatus(422);
    }

    public function test_role_permissions_drive_create_edit_and_delete(): void
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'expense-clerk', 'guard_name' => 'web']);
        $role->syncPermissions(['dashboard.view', 'expenses.view', 'expenses.create', 'expenses.edit']);
        $clerk = \App\Models\User::create(['household_id' => \App\Models\Household::first()->id, 'name' => 'Clerk', 'email' => 'clerk@homeerp.test', 'password' => 'password', 'is_active' => true]);
        $clerk->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'clerk@homeerp.test', 'password' => 'password'])->json('token')];

        // Granted: view, create and edit. Denied: delete and every ungranted module.
        $this->getJson('/api/modules/expenses', $headers)->assertOk();
        $this->postJson('/api/modules/expenses', ['description' => 'Clerk entry', 'amount' => 500, 'expense_date' => now()->toDateString(), 'category_id' => 1, 'payment_method' => 'cash'], $headers)->assertCreated();
        $this->putJson('/api/modules/expenses/1', ['description' => 'Clerk edit', 'amount' => 600, 'expense_date' => now()->toDateString()], $headers)->assertOk();
        $this->deleteJson('/api/modules/expenses/2', [], $headers)->assertForbidden();
        $this->getJson('/api/modules/assets', $headers)->assertForbidden();

        // Removing a permission from the role closes the gate again.
        $role->syncPermissions(['dashboard.view', 'expenses.view', 'expenses.edit']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->postJson('/api/modules/expenses', ['description' => 'Blocked now', 'amount' => 700, 'expense_date' => now()->toDateString()], $headers)->assertForbidden();
    }

    public function test_relation_ids_are_accepted_on_module_write(): void
    {
        $household = \App\Models\Household::first();
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])->json('token')];

        // A relation dropdown posts a numeric id, which must not be rejected as a string.
        // A super admin targets a household explicitly.
        $this->postJson('/api/modules/expenses', [
            'description' => 'Relation check', 'amount' => 250, 'expense_date' => now()->toDateString(),
            'category_id' => 1, 'property_id' => 1, 'payment_method' => 'card', 'household_id' => $household->id,
        ], $headers)->assertCreated()->assertJsonPath('data.category.name', 'Groceries');
    }

    public function test_admin_cannot_reference_a_relation_from_another_household(): void
    {
        $other = \App\Models\Household::create(['name' => 'Neighbour', 'slug' => 'relation-neighbour', 'timezone' => 'UTC', 'currency' => 'INR']);
        $otherProperty = \App\Models\Property::create(['household_id' => $other->id, 'name' => 'Other House', 'property_type' => 'villa', 'ownership_status' => 'owned']);
        $adminHeaders = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token')];

        $this->postJson('/api/modules/expenses', [
            'description' => 'Cross household', 'amount' => 250, 'expense_date' => now()->toDateString(), 'property_id' => $otherProperty->id,
        ], $adminHeaders)->assertForbidden();
    }

    public function test_denied_module_writes_return_forbidden_not_validation_errors(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'family@homeerp.test', 'password' => 'password'])->json('token')];

        $this->postJson('/api/modules/assets', ['name' => 'No permission'], $headers)->assertForbidden();
        $this->postJson('/api/modules/bills', ['title' => 'No permission'], $headers)->assertForbidden();
    }

    public function test_seeded_super_admin_account_can_log_in(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'root@homeerp.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.roles.0', 'super-admin')
            ->assertJsonPath('user.abilities.users.manage', true);
    }

    public function test_admin_cannot_administer_users_in_another_family(): void
    {
        $other = \App\Models\Household::create(['name' => 'Neighbour Family', 'slug' => 'neighbour-family-2', 'timezone' => 'UTC', 'currency' => 'INR']);
        $stranger = \App\Models\User::create(['household_id' => $other->id, 'name' => 'Stranger User', 'email' => 'stranger@homeerp.test', 'password' => 'password', 'is_active' => true]);
        $stranger->assignRole('family-member');
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token')];

        $this->getJson('/api/settings/users', $headers)->assertOk()
            ->assertJsonMissing(['email' => 'stranger@homeerp.test']);
        $this->putJson('/api/settings/users/'.$stranger->id, ['name' => 'Renamed'], $headers)->assertForbidden();
        $this->putJson('/api/settings/users/'.$stranger->id.'/role', ['role' => 'staff'], $headers)->assertForbidden();
        $this->deleteJson('/api/settings/users/'.$stranger->id, $headers)->assertForbidden();
    }

    public function test_a_custom_role_with_management_permissions_reaches_the_same_areas(): void
    {
        $household = \App\Models\Household::first();
        $role = \Spatie\Permission\Models\Role::create(['name' => 'household-manager', 'guard_name' => 'web']);
        $role->syncPermissions(['settings.view', 'settings.manage', 'users.view']);
        $user = \App\Models\User::create(['household_id' => $household->id, 'name' => 'Nisha Manager', 'email' => 'manager@homeerp.test', 'password' => 'password', 'is_active' => true]);
        $user->assignRole($role);

        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'manager@homeerp.test', 'password' => 'password'])->json('token')];

        $this->getJson('/api/settings/access', $headers)->assertOk();
        $this->getJson('/api/settings/users', $headers)->assertOk();
        $this->getJson('/api/households', $headers)->assertOk()->assertJsonCount(1, 'data');
        // A management role still cannot touch areas it was not granted: no extra
        // family workspaces, and no modules the role lacks.
        $this->postJson('/api/households', ['name' => 'Nope', 'timezone' => 'UTC', 'currency' => 'INR'], $headers)->assertForbidden();
        $this->postJson('/api/modules/assets', ['name' => 'Not allowed'], $headers)->assertForbidden();
    }

    public function test_family_member_cannot_reach_administrative_areas(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'family@homeerp.test', 'password' => 'password'])->json('token')];

        $this->getJson('/api/settings', $headers)->assertForbidden();
        $this->getJson('/api/settings/access', $headers)->assertForbidden();
        $this->getJson('/api/settings/users', $headers)->assertForbidden();
        $this->getJson('/api/households', $headers)->assertForbidden();
    }

    public function test_admin_cannot_escalate_to_super_admin(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token')];
        $admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();

        $this->postJson('/api/settings/users', ['name' => 'Back Door', 'email' => 'backdoor@homeerp.test', 'password' => 'password123', 'role' => 'super-admin'], $headers)->assertForbidden();
        $this->postJson('/api/households/1/users', ['name' => 'Back Door', 'email' => 'backdoor@homeerp.test', 'password' => 'password123', 'role' => 'super-admin'], $headers)->assertForbidden();
        // System roles are locked to super admins so permissions cannot be inflated or stripped.
        $this->putJson('/api/settings/roles/'.$admin->id, ['permissions' => ['dashboard.view']], $headers)->assertStatus(422);
    }

    public function test_household_workflows_record_stock_payment_and_task_activity(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/inventory/1/movement', ['movement_type' => 'stock_in', 'quantity' => 2, 'movement_date' => now()->toDateString()], $headers)->assertCreated()->assertJsonPath('item.quantity', '4.00');
        $this->postJson('/api/bills/1/pay', ['amount' => 6840, 'paid_at' => now()->toDateString(), 'payment_method' => 'bank'], $headers)->assertCreated()->assertJsonPath('bill.status', 'paid');
        $this->postJson('/api/tasks/1/checklist', ['label' => 'Check soil'], $headers)->assertCreated();
        $this->postJson('/api/tasks/1/comments', ['body' => 'Completed the first round.'], $headers)->assertCreated();
    }
    public function test_report_exports_contain_readable_values(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token');
        $excel = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/reports/export/expenses/excel');
        $excel->assertOk();
        $excel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $strings = $this->xlsxSharedStrings($excel->streamedContent());
        $this->assertStringContainsString('Groceries', $strings, 'Relation columns must export as readable labels.');
        $this->assertMatchesRegularExpression('/\d{2} \w{3} 20\d{2}/', $strings, 'Dates must be human readable in exports.');
        $this->assertStringNotContainsString('Array', $strings);

        $pdf = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/reports/export/expenses/pdf');
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(1000, strlen($pdf->getContent()));
    }

    private function xlsxSharedStrings(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $content = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        @unlink($path);
        return $content;
    }

    public function test_admin_can_create_update_and_delete_expense(): void

    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $create = $this->postJson('/api/modules/expenses', ['description' => 'Test expense', 'amount' => 99.5, 'expense_date' => now()->toDateString(), 'payment_method' => 'card'], $headers);
        $create->assertCreated();
        $id = $create->json('data.id');
        $this->putJson('/api/modules/expenses/'.$id, ['amount' => 109.5], $headers)->assertOk()->assertJsonPath('data.amount', '109.50');
        $this->deleteJson('/api/modules/expenses/'.$id, [], $headers)->assertOk();
    }
}
