<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_can_be_created_with_generated_code_and_metadata(): void
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

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/catalogs/categories', [
                'name' => 'Electrónica',
                'description' => 'Categoría de prueba',
                'image_url' => 'https://example.com/category.png',
                'level' => 1,
                'is_active' => false,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.name', 'Electrónica');
        $response->assertJsonPath('item.description', 'Categoría de prueba');
        $response->assertJsonPath('item.image_url', 'https://example.com/category.png');
        $response->assertJsonPath('item.level', 1);
        $this->assertNotEmpty($response->json('item.code'));
        $this->assertMatchesRegularExpression('/^CAT\d{6}$/', $response->json('item.code'));

        $this->assertDatabaseHas('categories', [
            'company_id' => $companyId,
            'name' => 'Electrónica',
            'description' => 'Categoría de prueba',
            'image_url' => 'https://example.com/category.png',
            'level' => 1,
            'is_active' => 0,
        ]);
    }
}
