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

    public function test_customer_can_be_created_with_extended_profile_fields(): void
    {
        $token = $this->authenticateUser();

        $countryId = DB::table('countries')->insertGetId([
            'iso2' => 'ES', 'iso3' => 'ESP', 'name' => 'España', 'phone_code' => '+34',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', [
                'full_name' => 'Cliente Extranjero',
                'status' => 1,
                'residency_type' => 'EXTRANJERO',
                'gender' => 'FEMENINO',
                'sales_type' => 'CONTADO',
                'phone' => '600-123-456',
                'phone_country_id' => $countryId,
                'has_landline' => true,
                'landline_phone' => '91 123 45 67',
                'country_id' => $countryId,
                'city' => 'Madrid',
                'applies_late_fee' => true,
                'late_fee_percentage' => 3.5,
                'late_fee_period_unit' => 'WEEKS',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.residency_type', 'EXTRANJERO');
        $response->assertJsonPath('item.gender', 'FEMENINO');
        $response->assertJsonPath('item.sales_type', 'CONTADO');
        $response->assertJsonPath('item.phone_display', '+34 600-123-456');
        $response->assertJsonPath('item.has_landline', true);
        $response->assertJsonPath('item.city', 'Madrid');
        $response->assertJsonPath('item.applies_late_fee', true);
        $response->assertJsonPath('item.late_fee_percentage', 3.5);
        $response->assertJsonPath('item.late_fee_period_unit', 'WEEKS');
    }

    public function test_customer_rejects_phone_with_invalid_characters(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', [
                'full_name' => 'Cliente Telefono Invalido',
                'status' => 1,
                'phone' => 'llamame-ya!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_customer_requires_late_fee_percentage_when_applies_late_fee_enabled(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', [
                'full_name' => 'Cliente Mora Incompleta',
                'status' => 1,
                'applies_late_fee' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['late_fee_percentage', 'late_fee_period_unit']);
    }

    public function test_customer_requires_landline_phone_when_has_landline_enabled(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', [
                'full_name' => 'Cliente Convencional Incompleto',
                'status' => 1,
                'has_landline' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('landline_phone');
    }

    public function test_customer_defaults_residency_and_sales_type_when_omitted(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customers', ['full_name' => 'Cliente Por Defecto', 'status' => 1]);

        $response->assertCreated();
        $response->assertJsonPath('item.residency_type', 'NACIONAL');
        $response->assertJsonPath('item.sales_type', 'CREDITO');
        $this->assertNotNull($response->json('item.registered_at'));
    }
}
