<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private function authenticateUser(array $modules = ['clientes']): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dolar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Tester',
            'description' => 'Rol de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($modules as $module) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module,
                'action_name' => 'manage',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'tester',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId,
            'name' => 'Test token',
            'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'test-token';
    }

    public function test_customer_can_be_created_and_listed_with_generated_code(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', [
                'full_name' => 'Cliente de Prueba',
                'email' => 'cliente@example.com',
                'credit_limit' => 5000,
                'status' => 1,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.code', 'CLI-000001');
        $this->assertEquals(5000, $response->json('item.credit_available'));
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Cliente de Prueba',
            'company_id' => $this->companyId,
            'current_balance' => 0,
        ]);

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/customers');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
    }

    public function test_customer_creation_requires_full_name(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', ['status' => 1]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('full_name');
    }

    public function test_customer_cannot_be_deleted_when_it_has_sales(): void
    {
        $token = $this->authenticateUser();

        $customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'CLI-000001',
            'full_name' => 'Cliente con ventas',
            'currency_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales')->insert([
            'company_id' => $this->companyId,
            'customer_id' => $customerId,
            'sale_number' => 'VTA-000001',
            'sale_date' => now()->toDateString(),
            'status' => 'DRAFT',
            'currency_id' => 1,
            'exchange_rate' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/customers/{$customerId}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('customers', ['id' => $customerId]);
    }

    public function test_customer_status_can_be_toggled(): void
    {
        $token = $this->authenticateUser();

        $customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'CLI-000001',
            'full_name' => 'Cliente Activo',
            'currency_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/customers/{$customerId}/status", ['status' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'status' => 0]);
    }
}
