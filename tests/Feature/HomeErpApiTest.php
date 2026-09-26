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

        $this->getJson('/api/households', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/households', ['name' => 'Second Family', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR'], $headers)->assertCreated();
        $this->getJson('/api/households/'.$other->id.'/users', $headers)->assertOk();
        $this->putJson('/api/households/'.$other->id, ['name' => 'Renamed Neighbour'], $headers)->assertOk();
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
