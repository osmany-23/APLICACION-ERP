<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalCatalogsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): array
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dólar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'usuario-test',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Test',
            'email' => 'test@example.com',
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

        return [$companyId, $currencyId];
    }

    private function authenticateUserWithCurrency(int $currencyId): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'usuario-test',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Test',
            'email' => 'test@example.com',
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

        return [$companyId, $currencyId];
    }

    public function test_currency_can_be_created_and_listed(): void
    {
        $this->authenticateUser();

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/settings/currencies', [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.code', 'EUR');
        $this->assertDatabaseHas('currencies', ['code' => 'EUR', 'name' => 'Euro']);

        $listResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/settings/currencies');

        $listResponse->assertOk();
        $this->assertGreaterThan(0, count($listResponse->json('data')));
    }

    public function test_payment_terms_can_be_created_without_company_scope(): void
    {
        $this->authenticateUser();

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/global-catalogs/payment-terms', [
                'name' => '30 días',
                'days' => 30,
                'discount_percent' => 5,
                'discount_days' => 10,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.name', '30 días');
        $this->assertDatabaseHas('payment_terms', ['name' => '30 días', 'days' => 30]);

        $listResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/global-catalogs/payment-terms');

        $listResponse->assertOk();
        $this->assertGreaterThan(0, count($listResponse->json('data')));
    }

    public function test_payment_methods_can_be_created_and_listed(): void
    {
        $this->authenticateUser();

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/global-catalogs/payment-methods', [
                'code' => 'TRF',
                'name' => 'Transferencia',
                'description' => 'Pago por transferencia bancaria',
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.code', 'TRF');
        $this->assertDatabaseHas('payment_methods', ['code' => 'TRF', 'name' => 'Transferencia']);

        $listResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/global-catalogs/payment-methods');

        $listResponse->assertOk();
        $this->assertGreaterThan(0, count($listResponse->json('data')));
    }

    public function test_company_currency_update_is_used_for_new_suppliers(): void
    {
        $baseCurrencyId = DB::table('currencies')->insertGetId([
            'code' => 'CRC',
            'name' => 'Colón',
            'symbol' => '₡',
            'decimal_places' => 2,
            'is_base' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetCurrencyId = DB::table('currencies')->insertGetId([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_base' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$companyId] = $this->authenticateUserWithCurrency($baseCurrencyId);

        $updateResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->putJson('/api/relations/companies/'.$companyId, [
                'name' => 'Empresa Test',
                'legal_name' => 'Empresa Test S.A.',
                'tax_id' => '123456789',
                'phone' => '8888-8888',
                'email' => 'info@example.com',
                'address' => 'Managua',
                'status' => 1,
                'currency_id' => $targetCurrencyId,
            ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('companies', ['id' => $companyId, 'currency_id' => $targetCurrencyId]);

        $supplierResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/relations/suppliers', [
                'name' => 'Proveedor Demo',
                'tax_id' => '111111111',
                'phone' => '2222-2222',
                'email' => 'supplier@example.com',
                'address' => 'León',
                'status' => 1,
            ]);

        $supplierResponse->assertCreated();
        $this->assertDatabaseHas('suppliers', ['company_id' => $companyId, 'currency_id' => $targetCurrencyId]);
    }
}
