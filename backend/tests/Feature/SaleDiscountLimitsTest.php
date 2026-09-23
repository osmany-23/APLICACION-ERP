<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre SalesService::resolveMaxLineDiscount(): el tope de descuento por
 * linea puede venir del producto (max_discount_type/max_discount_value),
 * del tope general de Configuracion General (sales.max_discount_enabled/
 * max_discount_percentage) o del vendedor (users.max_discount_percentage,
 * resuelto sobre salesperson_id). Cuando mas de uno aplica, gana el mas
 * restrictivo; el limite propio del producto reemplaza (no se suma) al
 * general.
 */
class SaleDiscountLimitsTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

    private int $userId;

    private function seedFixtures(): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'decimal_places' => 2,
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

        foreach (['facturacion', 'clientes', 'cajas'] as $module) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module, 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $roleId, 'uuid' => (string) Str::uuid(),
            'username' => 'tester', 'password_hash' => bcrypt('password'), 'full_name' => 'Tester',
            'email' => 'tester@example.com', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $this->userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'BOD-01', 'name' => 'Bodega Central',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-01',
            'name' => 'Sucursal Central', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cash_registers')->insert([
            'company_id' => $this->companyId, 'branch_id' => $branchId, 'code' => 'CAJA-01', 'name' => 'Caja #1',
            'default_currency_id' => $currencyId, 'status' => 'ACTIVA', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId, 'credit_limit' => 10000, 'current_balance' => 0, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15', 'name' => 'IVA 15%', 'rate' => 15, 'type' => 'VAT', 'is_default' => true,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 10, 'sale_price' => 25, 'tax_type' => 'EXEMPT', 'tax_percentage' => 0,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->productId,
            'quantity' => 100, 'reserved_quantity' => 0, 'average_cost' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('document_types')->insert([
            'code' => 'FACT', 'name' => 'Factura de Venta', 'prefix' => 'FACT', 'next_number' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    private function saleItemPayload(float $quantity, float $unitPrice, float $discount): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
        ];
    }

    private function setGeneralDiscountCap(bool $enabled, float $percentage): void
    {
        DB::table('company_settings')->updateOrInsert(
            ['company_id' => $this->companyId, 'group' => 'sales', 'key' => 'max_discount_enabled'],
            ['type' => 'boolean', 'is_encrypted' => false, 'value' => $enabled ? '1' : '0', 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('company_settings')->updateOrInsert(
            ['company_id' => $this->companyId, 'group' => 'sales', 'key' => 'max_discount_percentage'],
            ['type' => 'decimal', 'is_encrypted' => false, 'value' => (string) $percentage, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function test_discount_within_product_percentage_limit_is_allowed(): void
    {
        $token = $this->seedFixtures();

        DB::table('products')->where('id', $this->productId)->update([
            'max_discount_type' => 'PERCENTAGE', 'max_discount_value' => 10,
        ]);

        // Linea: 4 * 25 = 100. 8% de descuento = 8, dentro del limite de 10%.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 8)],
            ]);

        $response->assertCreated();
    }

    public function test_discount_exceeding_product_percentage_limit_is_rejected(): void
    {
        $token = $this->seedFixtures();

        DB::table('products')->where('id', $this->productId)->update([
            'max_discount_type' => 'PERCENTAGE', 'max_discount_value' => 10,
        ]);

        // Linea: 4 * 25 = 100. 15% de descuento = 15, supera el limite de 10%.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 15)],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'El descuento para "Producto de Prueba" (15.00) supera el maximo permitido (10.00).']);
    }

    public function test_discount_exceeding_product_fixed_limit_is_rejected(): void
    {
        $token = $this->seedFixtures();

        // Tope fijo de C$2 por unidad; con 4 unidades el maximo de la linea es C$8.
        DB::table('products')->where('id', $this->productId)->update([
            'max_discount_type' => 'FIXED', 'max_discount_value' => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 10)],
            ]);

        $response->assertStatus(422);

        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 8)],
            ]);

        $response2->assertCreated();
    }

    public function test_general_discount_cap_applies_only_when_product_has_no_specific_limit(): void
    {
        $token = $this->seedFixtures();
        $this->setGeneralDiscountCap(true, 5);

        // Producto sin limite propio: hereda el 5% general. Linea de 100,
        // 6 de descuento (6%) supera el 5% general.
        $rejected = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 6)],
            ]);
        $rejected->assertStatus(422);

        $allowed = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 4)],
            ]);
        $allowed->assertCreated();
    }

    public function test_product_specific_limit_overrides_the_general_cap_instead_of_stacking(): void
    {
        $token = $this->seedFixtures();
        $this->setGeneralDiscountCap(true, 5);

        // El producto trae su propio 20%, que reemplaza (no se suma) al 5%
        // general: un descuento de 15% debe permitirse aunque supere el
        // tope general.
        DB::table('products')->where('id', $this->productId)->update([
            'max_discount_type' => 'PERCENTAGE', 'max_discount_value' => 20,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 15)],
            ]);

        $response->assertCreated();
    }

    public function test_seller_discount_cap_applies_even_without_any_product_limit(): void
    {
        $token = $this->seedFixtures();

        DB::table('users')->where('id', $this->userId)->update(['max_discount_percentage' => 2]);

        // Linea de 100, 3% de descuento supera el limite personal del
        // vendedor (2%), aunque el producto no tenga ningun limite propio
        // y el tope general este desactivado.
        $rejected = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 3)],
            ]);
        $rejected->assertStatus(422);

        $allowed = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 2)],
            ]);
        $allowed->assertCreated();
    }

    public function test_most_restrictive_of_seller_and_product_caps_wins(): void
    {
        $token = $this->seedFixtures();

        DB::table('users')->where('id', $this->userId)->update(['max_discount_percentage' => 2]);
        DB::table('products')->where('id', $this->productId)->update([
            'max_discount_type' => 'PERCENTAGE', 'max_discount_value' => 10,
        ]);

        // El producto permitiria hasta 10%, pero el vendedor solo tiene 2%
        // — debe ganar el mas restrictivo (2%).
        $rejected = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 5)],
            ]);
        $rejected->assertStatus(422);

        $allowed = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(4, 25, 2)],
            ]);
        $allowed->assertCreated();
    }

    public function test_discount_cap_is_validated_against_the_resolved_salesperson_not_the_logged_in_user(): void
    {
        $token = $this->seedFixtures();

        // El usuario logueado (tester) no tiene limite propio, pero el
        // vendedor identificado por PIN (Carlos) si tiene uno de 1% — la
        // venta debe validarse contra Carlos, no contra tester.
        $salespersonRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Vendedor', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $carlosId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $salespersonRoleId, 'uuid' => (string) Str::uuid(),
            'username' => 'carlos', 'password_hash' => bcrypt('password'), 'full_name' => 'Carlos',
            'status' => 1, 'max_discount_percentage' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Linea de 100, 2% de descuento: dentro de lo que permitiria el
        // usuario logueado (sin limite) pero por encima del 1% de Carlos.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'salesperson_id' => $carlosId,
                'items' => [$this->saleItemPayload(4, 25, 2)],
            ]);

        $response->assertStatus(422);
    }
}
