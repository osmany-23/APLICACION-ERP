<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneralSettingsIvaTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

    /**
     * Empresa, rol "Administrador" (bypassa el chequeo de permisos de
     * authorizeGeneralSettings()), usuario, almacen, cliente, producto
     * TAXABLE, y la fila IVA15 sembrada tal como la deja ErpBaseSeeder en
     * produccion (is_default=true, is_active=true, rate=15%).
     */
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
            'company_id' => $this->companyId, 'name' => 'Administrador', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // "Administrador" ya bypassa authorizeGeneralSettings() por nombre
        // de rol, pero SaleController::authorizeSales() exige el permiso
        // real de facturacion (no mira el nombre del rol) — se agrega para
        // poder crear ventas en las pruebas de aplicacion automatica.
        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'facturacion', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
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

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId, 'credit_limit' => 10000, 'current_balance' => 0,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->updateOrInsert(
            ['code' => 'IVA15'],
            [
                'name' => 'Impuesto al Valor Agregado 15%', 'rate' => 15.0000, 'type' => 'VAT',
                'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
        );

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 10, 'sale_price' => 100, 'tax_type' => 'TAXABLE', 'tax_percentage' => 15,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->productId,
            'quantity' => 100, 'reserved_quantity' => 0, 'average_cost' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    public function test_changing_the_general_iva_rate_updates_the_default_tax_row(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/settings/general', [
                'sales' => ['iva_enabled' => true, 'iva_rate' => 10],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.sales.iva_enabled', true);
        $response->assertJsonPath('data.settings.sales.iva_rate', 10);

        $this->assertDatabaseHas('taxes', [
            'code' => 'IVA15', 'rate' => 10.0000, 'is_default' => true, 'is_active' => true,
        ]);
    }

    public function test_new_general_iva_rate_is_applied_automatically_when_invoicing(): void
    {
        $token = $this->seedFixtures();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/settings/general', [
                'sales' => ['iva_enabled' => true, 'iva_rate' => 10],
            ])->assertOk();

        $sale = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [['product_id' => $this->productId, 'quantity' => 1, 'unit_price' => 100]],
            ]);

        $sale->assertCreated();
        // 100 + 10% (la nueva tasa general, no el 15% original) = 110.
        $sale->assertJsonPath('item.tax', 10);
        $sale->assertJsonPath('item.total', 110);
    }

    public function test_disabling_the_general_iva_stops_it_from_being_applied(): void
    {
        $token = $this->seedFixtures();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/settings/general', [
                'sales' => ['iva_enabled' => false, 'iva_rate' => 15],
            ])->assertOk();

        $this->assertDatabaseHas('taxes', ['code' => 'IVA15', 'is_active' => false]);

        $sale = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [['product_id' => $this->productId, 'quantity' => 1, 'unit_price' => 100]],
            ]);

        $sale->assertCreated();
        $sale->assertJsonPath('item.tax', 0);
        $sale->assertJsonPath('item.total', 100);
    }
}
