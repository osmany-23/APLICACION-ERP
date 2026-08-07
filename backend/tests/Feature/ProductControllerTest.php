<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $categoryId;

    private int $brandId;

    private int $unitId;

    private int $supplierId;

    /**
     * Regresion del bug confirmado: ProductController::createInventoryMovement()
     * y movements() referenciaban columnas inexistentes en inventory_movements
     * (inventory_status, lot_number, expiration_date) y valores de enum
     * ('ENTRY'/'EXIT') que no existen, lo cual hacia fallar con SQL error
     * cualquier alta de producto con stock inicial.
     */
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

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'products', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
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

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'BOD-01', 'name' => 'Bodega Central',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->categoryId = DB::table('categories')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CAT001', 'name' => 'General',
            'level' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->brandId = DB::table('brands')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'BR001', 'name' => 'Generica',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->unitId = DB::table('units')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'UND', 'name' => 'Unidad', 'short_name' => 'UND',
            'category' => 'UNIDAD', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->supplierId = DB::table('suppliers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PRV-000001', 'name' => 'Proveedor Test',
            'currency_id' => $currencyId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return 'test-token';
    }

    public function test_creating_product_with_initial_stock_registers_inventory_movement(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto de Prueba',
                'code' => 'PROD-001',
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId,
                'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1,
                'sale_price' => 50,
                'cost' => 30,
                'tax_type' => 'EXEMPT',
                'initial_stock' => 20,
                'inventory_status' => 'RECEIVED',
                'minimum_stock' => 5,
                'status' => 'Activo',
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId,
            'movement_type' => 'INITIAL_INVENTORY',
            'quantity' => 20,
        ]);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => 20,
        ]);

        $movements = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$productId}/movements");

        $movements->assertOk();
        $movements->assertJsonCount(1, 'data');
        $movements->assertJsonPath('data.0.type', 'INITIAL_INVENTORY');
        $movements->assertJsonPath('data.0.input', 20);
    }
}
