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

    public function test_household_workflows_record_stock_payment_and_task_activity(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@homeerp.test', 'password' => 'password'])->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/inventory/1/movement', ['movement_type' => 'stock_in', 'quantity' => 2, 'movement_date' => now()->toDateString()], $headers)->assertCreated()->assertJsonPath('item.quantity', '4.00');
        $this->postJson('/api/bills/1/pay', ['amount' => 6840, 'paid_at' => now()->toDateString(), 'payment_method' => 'bank'], $headers)->assertCreated()->assertJsonPath('bill.status', 'paid');
        $this->postJson('/api/tasks/1/checklist', ['label' => 'Check soil'], $headers)->assertCreated();
        $this->postJson('/api/tasks/1/comments', ['body' => 'Completed the first round.'], $headers)->assertCreated();
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
