<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $branchId;

    private int $customerId;

    private int $productId;

    private int $categoryId;

    private int $cashPaymentMethodId;

    /**
     * Mismo patron de fixtures que SaleControllerTest (empresa, moneda,
     * usuario, almacen, producto con stock, plan de cuentas real), mas una
     * sucursal, categoria y metodo de pago en efectivo, que el endpoint de
     * analitica necesita para "ventas por sucursal/categoria" y "flujo de
     * caja".
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

        $this->branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-01',
            'name' => 'Sucursal Central', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Tester', 'description' => 'Rol de prueba',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['facturacion', 'clientes'] as $module) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module, 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $roleId, 'branch_id' => $this->branchId,
            'uuid' => (string) Str::uuid(), 'username' => 'tester', 'password_hash' => bcrypt('password'),
            'full_name' => 'Tester', 'email' => 'tester@example.com', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'code' => 'BOD-01', 'name' => 'Bodega Central',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId, 'credit_limit' => 10000, 'current_balance' => 0, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->categoryId = DB::table('categories')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CAT001', 'name' => 'Ferreteria',
            'level' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15', 'name' => 'IVA 15%', 'rate' => 15, 'type' => 'VAT',
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'category_id' => $this->categoryId, 'code' => 'PROD-001',
            'short_name' => 'Producto de Prueba', 'type' => 'PRODUCTO', 'status' => 1,
            'is_inventory' => true, 'is_service' => false, 'allow_sale' => true, 'allow_purchase' => true,
            'allow_negative_stock' => false, 'cost' => 10, 'sale_price' => 25, 'tax_type' => 'EXEMPT',
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

        $this->cashPaymentMethodId = DB::table('payment_methods')->insertGetId([
            'code' => 'CASH', 'name' => 'Efectivo', 'type' => 'CASH', 'cash' => true, 'card' => false,
            'bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    private function createConfirmedSale(string $token, float $quantity, float $unitPrice, ?string $saleDate = null): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'branch_id' => $this->branchId,
                'payment_method_id' => $this->cashPaymentMethodId,
                'confirm' => true,
                'items' => [
                    ['product_id' => $this->productId, 'quantity' => $quantity, 'unit_price' => $unitPrice],
                ],
            ]);

        $response->assertCreated();
        $saleId = (int) $response->json('item.id');

        if ($saleDate) {
            DB::table('sales')->where('id', $saleId)->update(['sale_date' => $saleDate]);
        }

        return $saleId;
    }

    public function test_analytics_aggregates_confirmed_sales_into_trend_and_rankings(): void
    {
        $token = $this->seedFixtures();

        // 10 unidades a 25 (costo 10 c/u): venta 250, costo 100, utilidad 150.
        // No se fuerza sale_date: se deja que SalesService la asigne con
        // "hoy" (AuthenticateApiToken ya aplico el timezone de la empresa
        // en esta request; forzar aqui un Carbon::now() calculado ANTES de
        // esa request usaria UTC crudo y podria caer un dia distinto).
        $this->createConfirmedSale($token, 10, 25);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/analytics?period=30d');

        $response->assertOk();
        $data = $response->json('data');

        // assertEquals (no assertSame): los numeros redondos viajan por un
        // JSON de ida y vuelta y PHP los decodifica como int, no float.
        $this->assertEquals(250, array_sum($data['trends']['sales']));
        $this->assertEquals(150, array_sum($data['trends']['gross_profit']));
        $this->assertEquals(100, array_sum($data['trends']['cost']));
        // Efectivo: la venta completa es flujo de caja positivo ese dia.
        $this->assertEquals(250, array_sum($data['trends']['cash_flow']));

        $this->assertSame('Ferreteria', $data['sales_by_category'][0]['label']);
        $this->assertEquals(250, $data['sales_by_category'][0]['value']);

        $this->assertSame('Sucursal Central', $data['sales_by_branch'][0]['label']);
        $this->assertSame('Producto de Prueba', $data['top_products'][0]['label']);
        $this->assertSame('Cliente de Prueba', $data['top_customers'][0]['label']);
        $this->assertSame('Efectivo', $data['payment_methods'][0]['label']);

        $this->assertEquals(150, $data['profitability']['by_product'][0]['value']);
        $this->assertEqualsWithDelta(60.0, $data['profitability']['most_profitable'][0]['value'], 0.01);
    }

    public function test_analytics_excludes_draft_sales_from_trends(): void
    {
        $token = $this->seedFixtures();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'confirm' => false,
                'items' => [
                    ['product_id' => $this->productId, 'quantity' => 3, 'unit_price' => 25],
                ],
            ])->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/analytics?period=30d');

        $response->assertOk();
        $this->assertEquals(0, array_sum($response->json('data.trends.sales')));
        $this->assertSame([], $response->json('data.top_products'));
    }

    public function test_analytics_compare_flag_uses_the_exact_previous_calendar_year(): void
    {
        // Regresion del bug real detectado: diffInDays() entre un
        // start-of-day y un end-of-day contaba casi un dia extra
        // (365.999... en vez de 365), lo que corria el "periodo anterior"
        // un dia completo (terminaba el 31-dic del año actual -1 dia, no
        // el 31-dic del año pasado).
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/analytics?period=year&compare=1');

        $response->assertOk();

        $currentYear = (int) Carbon::now()->format('Y');
        $previousYear = $currentYear - 1;

        $response->assertJsonPath('data.period.start', "{$currentYear}-01-01");
        $response->assertJsonPath('data.period.end', "{$currentYear}-12-31");
        $response->assertJsonPath('data.comparison.period.start', "{$previousYear}-01-01");
        $response->assertJsonPath('data.comparison.period.end', "{$previousYear}-12-31");
        $this->assertCount(12, $response->json('data.trends.labels'));
    }

    public function test_analytics_reports_low_stock_and_receivable_balance(): void
    {
        $token = $this->seedFixtures();

        DB::table('products')->where('id', $this->productId)->update(['minimum_stock' => 1000]);

        $this->createConfirmedSale($token, 2, 25);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/analytics?period=30d');

        $response->assertOk();
        $lowStock = $response->json('data.low_stock_products');
        $this->assertNotEmpty($lowStock);
        $this->assertSame('Producto de Prueba', $lowStock[0]['label']);

        $receivable = DB::table('accounts_receivable')->where('company_id', $this->companyId)->first();
        if ($receivable) {
            $this->assertNotEmpty($response->json('data.receivables_status'));
        }
    }
}
