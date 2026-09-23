<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriceListControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private function authenticateUser(): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD', 'name' => 'Dolar', 'symbol' => '$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Tester', 'description' => 'Rol de prueba',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $roleId, 'uuid' => (string) Str::uuid(),
            'username' => 'tester', 'password_hash' => bcrypt('password'), 'full_name' => 'Tester',
            'email' => 'tester@example.com', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return 'test-token';
    }

    public function test_creating_a_price_type_persists_it_with_an_auto_generated_code(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/settings/price-types', [
                'name' => 'Precio Mayorista',
                'description' => 'Para clientes de volumen',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.name', 'Precio Mayorista');
        $response->assertJsonPath('item.code', 'PRECIO-MAYORISTA');
        $response->assertJsonPath('item.is_active', true);

        $this->assertDatabaseHas('price_lists', [
            'company_id' => $this->companyId,
            'code' => 'PRECIO-MAYORISTA',
            'name' => 'Precio Mayorista',
        ]);
    }

    public function test_price_types_are_scoped_to_the_authenticated_users_company(): void
    {
        $token = $this->authenticateUser();

        $otherCompanyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Otra Empresa',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('price_lists')->insert([
            'company_id' => $otherCompanyId, 'code' => 'AJENO', 'name' => 'Tipo de otra empresa',
            'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $mine = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/settings/price-types', ['name' => 'Mio']);
        $mine->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings/price-types');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mio'));
        $this->assertFalse($names->contains('Tipo de otra empresa'));
    }

    public function test_deleting_a_price_type_in_use_by_a_product_is_blocked(): void
    {
        $token = $this->authenticateUser();

        $priceListId = DB::table('price_lists')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'MAYORISTA', 'name' => 'Mayorista',
            'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'status' => 1, 'is_inventory' => true, 'allow_sale' => true, 'allow_purchase' => true,
            'cost' => 10, 'sale_price' => 25, 'tax_type' => 'EXEMPT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('product_price_list_items')->insert([
            'product_id' => $productId, 'variant_id' => null, 'price_list_id' => $priceListId,
            'min_quantity' => 10, 'price' => 20, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/settings/price-types/{$priceListId}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('price_lists', ['id' => $priceListId]);
    }
}
