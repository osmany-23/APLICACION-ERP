<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

    private int $kitComponentAId;

    private int $kitComponentBId;

    private int $cashRegisterId;

    /**
     * Crea empresa, moneda, rol con permisos de facturacion/clientes,
     * usuario autenticado, almacen, producto con stock inicial (100 uds a
     * costo 10), impuesto IVA 15% por defecto, y el plan de cuentas
     * contable (via el seeder real) para que JournalPostingService pueda
     * resolver las cuentas por codigo.
     */
    private function seedFixtures(float $stockQuantity = 100, float $averageCost = 10): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO',
            'name' => 'Cordoba',
            'symbol' => 'C$',
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

        foreach (['facturacion', 'clientes', 'cajas'] as $module) {
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

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'BOD-01',
            'name' => 'Bodega Central',
            'type' => 'PRINCIPAL',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId,
            'uuid' => (string) Str::uuid(),
            'code' => 'SUC-01',
            'name' => 'Sucursal Central',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cashRegisterId = DB::table('cash_registers')->insertGetId([
            'company_id' => $this->companyId,
            'branch_id' => $branchId,
            'code' => 'CAJA-01',
            'name' => 'Caja #1',
            'default_currency_id' => $currencyId,
            'status' => 'ACTIVA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'CLI-000001',
            'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId,
            'credit_limit' => 10000,
            'current_balance' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15',
            'name' => 'IVA 15%',
            'rate' => 15,
            'type' => 'VAT',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'PROD-001',
            'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO',
            'status' => 1,
            'is_inventory' => true,
            'is_service' => false,
            'allow_sale' => true,
            'allow_purchase' => true,
            'allow_negative_stock' => false,
            'cost' => $averageCost,
            'sale_price' => 25,
            'tax_type' => 'TAXABLE',
            'tax_percentage' => 15,
            'cost_method' => 'AVERAGE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId,
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'quantity' => $stockQuantity,
            'reserved_quantity' => 0,
            'average_cost' => $averageCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('document_types')->insert([
            'code' => 'FACT',
            'name' => 'Factura de Venta',
            'prefix' => 'FACT',
            'next_number' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    private function saleItemPayload(float $quantity = 5, ?float $unitPrice = null): array
    {
        return array_filter([
            'product_id' => $this->productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ], fn ($value) => $value !== null);
    }

    /**
     * Usuario con permiso "eliminar"/"administrar" sobre facturacion y PIN
     * propio (distinto del rol "manage" de seedFixtures(), que a proposito
     * no alcanza para anular). Sirve para probar el candado nuevo de
     * SalesService::resolveAuthorizedCanceller().
     */
    private function createAdminWithPin(string $plainPin = '1234'): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Administrador', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'facturacion', 'action_name' => 'administrar', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'admin-pin',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Administrador Con Pin',
            'status' => 1,
            'pin_hash' => Hash::make($plainPin),
            'pin_generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * "Combo/Kit" para probar la explosion de inventario al vender: un
     * producto sin existencia propia (is_inventory=false) compuesto por dos
     * componentes reales con su propio stock (product_kit_items). Requiere
     * que seedFixtures() ya haya corrido (usa $this->companyId/warehouseId).
     */
    private function seedKitFixtures(): int
    {
        $this->kitComponentAId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'COMP-A', 'short_name' => 'Componente A',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false, 'is_kit' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 5, 'sale_price' => 8, 'tax_type' => 'EXEMPT', 'tax_percentage' => 0,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->kitComponentAId,
            'quantity' => 50, 'reserved_quantity' => 0, 'average_cost' => 5, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->kitComponentBId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'COMP-B', 'short_name' => 'Componente B',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false, 'is_kit' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 3, 'sale_price' => 6, 'tax_type' => 'EXEMPT', 'tax_percentage' => 0,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->kitComponentBId,
            'quantity' => 20, 'reserved_quantity' => 0, 'average_cost' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $kitId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'KIT-001', 'short_name' => 'Combo Prueba',
            'type' => 'KIT', 'status' => 1, 'is_inventory' => false, 'is_service' => false, 'is_kit' => true,
            'allow_sale' => true, 'allow_purchase' => false, 'allow_negative_stock' => false,
            'cost' => 0, 'sale_price' => 40, 'tax_type' => 'EXEMPT', 'tax_percentage' => 0,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('product_kit_items')->insert([
            [
                'company_id' => $this->companyId, 'kit_product_id' => $kitId,
                'component_product_id' => $this->kitComponentAId, 'quantity' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'company_id' => $this->companyId, 'kit_product_id' => $kitId,
                'component_product_id' => $this->kitComponentBId, 'quantity' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        return $kitId;
    }

    public function test_confirmed_cash_sale_reduces_stock_and_registers_movement(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.status', 'COMPLETED');

        $this->assertDatabaseHas('inventory_stock', [
            'company_id' => $this->companyId,
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'quantity' => 90,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $this->companyId,
            'product_id' => $this->productId,
            'movement_type' => 'SALE_EXIT',
            'quantity' => 10,
            'stock_before' => 100,
            'stock_after' => 90,
        ]);
    }

    public function test_confirmed_sale_generates_a_balanced_journal_entry(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        $journalEntryId = $response->json('item.journal_entry_id');

        $this->assertNotNull($journalEntryId);
        $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'POSTED']);

        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $journalEntryId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
        $this->assertGreaterThan(0, (float) $totals->total_debit);
    }

    public function test_sale_with_shipping_adds_it_to_the_total_and_keeps_the_journal_entry_balanced(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'shipping' => 50,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        // 10 * 25 = 250 subtotal, +15% IVA (37.5) del fixture, + 50 de envio = 337.5.
        $response->assertJsonPath('item.shipping', 50);
        $response->assertJsonPath('item.total', 337.5);

        // El asiento tiene ademas la pata de costo de venta (COGS), asi que
        // total_debit incluye eso ademas del total de la venta — lo que
        // realmente importa es que cuadre contra total_credit.
        $journalEntryId = $response->json('item.journal_entry_id');
        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $journalEntryId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
        $this->assertGreaterThanOrEqual(337.5, (float) $totals->total_debit);
    }

    public function test_exchange_rate_endpoint_returns_latest_usd_rate_for_the_company(): void
    {
        $token = $this->seedFixtures();

        $usdId = DB::table('currencies')->insertGetId([
            'code' => 'USD', 'name' => 'Dolar', 'symbol' => '$', 'decimal_places' => 2,
            'is_base' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $baseCurrencyId = DB::table('companies')->where('id', $this->companyId)->value('currency_id');

        // Dos tasas en fechas distintas: debe devolver la mas reciente (36.60), no la vieja (36.10).
        DB::table('exchange_rates')->insert([
            [
                'company_id' => $this->companyId, 'from_currency_id' => $usdId, 'to_currency_id' => $baseCurrencyId,
                'rate' => 36.10, 'date' => '2026-01-01', 'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'company_id' => $this->companyId, 'from_currency_id' => $usdId, 'to_currency_id' => $baseCurrencyId,
                'rate' => 36.60, 'date' => '2026-02-01', 'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/sales/exchange-rate');

        $response->assertOk();
        $response->assertJsonPath('data.rate', 36.6);
        $response->assertJsonPath('data.foreign_currency.code', 'USD');
        $response->assertJsonPath('data.base_currency.code', 'NIO');
    }

    public function test_exchange_rate_endpoint_returns_null_when_none_configured(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/sales/exchange-rate');

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }

    public function test_credit_sale_creates_accounts_receivable_and_updates_customer_balance(): void
    {
        $token = $this->seedFixtures();

        $paymentTermId = DB::table('payment_terms')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'CR30',
            'name' => 'Credito 30 dias',
            'days' => 30,
            'type' => 'CREDIT',
            'credit' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_term_id' => $paymentTermId,
                'items' => [$this->saleItemPayload(4, 25)],
            ]);

        $response->assertCreated();
        $total = $response->json('item.total');
        $receivableId = $response->json('item.accounts_receivable_id');

        $this->assertNotNull($receivableId);
        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $receivableId,
            'customer_id' => $this->customerId,
            'status' => 'PENDING',
            'balance' => $total,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $this->customerId,
            'current_balance' => $total,
        ]);
    }

    public function test_cash_sale_does_not_create_accounts_receivable(): void
    {
        $token = $this->seedFixtures();

        $paymentMethodId = DB::table('payment_methods')->insertGetId([
            'code' => 'CASH',
            'name' => 'Efectivo',
            'type' => 'CASH',
            'cash' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $paymentMethodId,
                'items' => [$this->saleItemPayload(2, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.accounts_receivable_id', null);
        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 0]);
        $this->assertDatabaseCount('accounts_receivable', 0);
    }

    public function test_cash_sale_to_an_ir_withholding_customer_splits_the_journal_debit_line(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        DB::table('customers')->where('id', $this->customerId)->update([
            'ir_withholding_agent' => true,
            'ir_withholding_rate' => 2.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        // 10 * 25 = 250 + 15% IVA (37.5) = 287.5. Retencion 2% del total = 5.75.
        $response->assertJsonPath('item.total', 287.5);
        $response->assertJsonPath('item.ir_withholding_rate', 2);
        $response->assertJsonPath('item.ir_withholding_amount', 5.75);
        $response->assertJsonPath('item.paid_amount', 287.5);
        $response->assertJsonPath('item.balance_due', 0);

        $journalEntryId = $response->json('item.journal_entry_id');
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journalEntryId,
            'debit' => 281.75, // 287.5 - 5.75
        ]);
        $retentionAccountId = DB::table('accounting_accounts')->where('code', '1106')->value('id');
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journalEntryId,
            'accounting_account_id' => $retentionAccountId,
            'debit' => 5.75,
        ]);

        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $journalEntryId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
    }

    public function test_cash_sale_to_a_non_withholding_customer_has_no_retention(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.ir_withholding_amount', 0);
        $response->assertJsonPath('item.ir_withholding_rate', null);
    }

    public function test_credit_sale_to_an_ir_withholding_customer_does_not_calculate_retention(): void
    {
        $token = $this->seedFixtures();

        DB::table('customers')->where('id', $this->customerId)->update([
            'ir_withholding_agent' => true,
            'ir_withholding_rate' => 2.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.ir_withholding_amount', 0);
        $response->assertJsonPath('item.ir_withholding_rate', null);
        $this->assertNotNull($response->json('item.accounts_receivable_id'));
    }

    public function test_sale_fails_when_stock_is_insufficient(): void
    {
        $token = $this->seedFixtures(stockQuantity: 5);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId,
            'quantity' => 5,
        ]);
    }

    public function test_sale_fails_when_customer_exceeds_credit_limit(): void
    {
        $token = $this->seedFixtures();

        DB::table('customers')->where('id', $this->customerId)->update(['credit_limit' => 50]);

        $paymentTermId = DB::table('payment_terms')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'CR30',
            'name' => 'Credito 30 dias',
            'days' => 30,
            'type' => 'CREDIT',
            'credit' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_term_id' => $paymentTermId,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('accounts_receivable', 0);
    }

    public function test_sale_can_be_attributed_to_a_different_salesperson_via_pin(): void
    {
        $token = $this->seedFixtures();

        $salespersonRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $salespersonId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $salespersonRoleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'cajero-pin',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Cajero Con Pin',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'salesperson_id' => $salespersonId,
                'confirm' => false,
                'items' => [$this->saleItemPayload(1)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.salesperson_id', $salespersonId);
        $this->assertNotEquals($response->json('item.created_by'), $response->json('item.salesperson_id'));
    }

    public function test_sale_rejects_salesperson_from_another_company(): void
    {
        $token = $this->seedFixtures();

        $otherCompanyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Otra Empresa', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignUserId = DB::table('users')->insertGetId([
            'company_id' => $otherCompanyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'foraneo',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario De Otra Empresa',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'salesperson_id' => $foreignUserId,
                'confirm' => false,
                'items' => [$this->saleItemPayload(1)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    /**
     * Crea un tipo de precio "Mayorista" y un precio por volumen para el
     * producto del fixture: a partir de $minQuantity, el precio pasa a ser
     * $price (mientras que sale_price sigue siendo 25, ver seedFixtures()).
     */
    private function seedPriceTier(float $minQuantity, float $price): void
    {
        $priceListId = DB::table('price_lists')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'MAYORISTA',
            'name' => 'Mayorista',
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_price_list_items')->insert([
            'product_id' => $this->productId,
            'variant_id' => null,
            'price_list_id' => $priceListId,
            'min_quantity' => $minQuantity,
            'price' => $price,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sale_applies_the_best_applicable_tier_price_when_quantity_meets_the_threshold(): void
    {
        $token = $this->seedFixtures();
        $this->seedPriceTier(minQuantity: 10, price: 20);

        // Sin unit_price: debe autocompletar con el precio del tier (20),
        // no con sale_price (25), porque la cantidad (10) alcanza el umbral.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.items.0.unit_price', 20);
    }

    public function test_sale_at_exactly_the_tier_threshold_quantity_applies_the_tier_price(): void
    {
        $token = $this->seedFixtures();
        $this->seedPriceTier(minQuantity: 10, price: 20);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.items.0.unit_price', 20);
    }

    public function test_sale_is_rejected_when_unit_price_is_below_the_applicable_tier_floor(): void
    {
        $token = $this->seedFixtures();
        $this->seedPriceTier(minQuantity: 10, price: 20);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(10, 15)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_is_rejected_when_unit_price_is_below_base_sale_price_and_no_tier_qualifies(): void
    {
        $token = $this->seedFixtures();
        $this->seedPriceTier(minQuantity: 10, price: 20);

        // Cantidad 1 no alcanza el umbral del tier (10), asi que el minimo
        // permitido cae a sale_price (25). Esto confirma que el cambio no
        // solo protege ventas de mayoreo: cualquier unit_price manual por
        // debajo del precio base tambien se rechaza ahora.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(1, 20)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    private function openCashSession(string $token, float $amount = 0): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $this->cashRegisterId,
                'opening_amount' => $amount,
            ]);

        $response->assertCreated();

        return (int) $response->json('item.id');
    }

    private function createPaymentMethod(string $code = 'CASH', string $name = 'Efectivo', bool $cash = true): int
    {
        return DB::table('payment_methods')->insertGetId([
            'code' => $code, 'name' => $name, 'type' => $cash ? 'CASH' : 'BANK_TRANSFER',
            'cash' => $cash, 'bank' => ! $cash, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pending_payment_sale_reduces_stock_but_stays_pending_with_no_ar_or_journal(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.status', 'PENDING');
        $response->assertJsonPath('item.transaction_status', 'PENDING_PAYMENT');
        $response->assertJsonPath('item.paid_amount', 0);
        // 250 de subtotal + 15% IVA del fixture (producto TAXABLE) = 287.5.
        $response->assertJsonPath('item.balance_due', 287.5);
        $response->assertJsonPath('item.journal_entry_id', null);

        $saleId = $response->json('item.id');

        // El inventario SI se mueve de inmediato (el producto ya se entrega).
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->productId, 'movement_type' => 'SALE_EXIT', 'quantity' => 10,
        ]);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 90,
        ]);

        // Pero NO es credito real: no crea cuenta por cobrar ni toca el
        // balance del cliente.
        $this->assertDatabaseCount('accounts_receivable', 0);
        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 0]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'journal_entry_id' => null]);
    }

    public function test_creating_pending_payment_sale_with_a_payment_method_is_rejected(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_creating_pending_payment_sale_as_draft_is_rejected(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'confirm' => false,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_pending_payment_sale_is_excluded_from_dashboard_analytics(): void
    {
        $token = $this->seedFixtures();

        // Una venta de contado normal (SI debe contar) y una pendiente de
        // pago (NO debe contar) por el mismo monto, el mismo dia.
        $cashMethodId = $this->createPaymentMethod();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(1, 25)],
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ])->assertCreated();

        $analytics = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/analytics?period=today');

        $analytics->assertOk();
        $totalSales = array_sum($analytics->json('data.trends.sales'));
        // 25 + 15% IVA del fixture = 28.75. Si la pendiente de pago tambien
        // contara, esto daria el doble (57.5).
        $this->assertEquals(28.75, $totalSales);
    }

    public function test_registering_the_full_balance_as_one_payment_completes_a_pending_sale_and_posts_journal_entry(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);
        $saleId = $created->json('item.id');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 287.5, 'payment_method_id' => $cashMethodId]);

        $payment->assertOk();
        $payment->assertJsonPath('item.status', 'COMPLETED');
        $payment->assertJsonPath('item.transaction_status', 'PAID');
        $payment->assertJsonPath('item.paid_amount', 287.5);
        $payment->assertJsonPath('item.balance_due', 0);
        $this->assertNotNull($payment->json('item.payment_confirmed_at'));

        $journalEntryId = $payment->json('item.journal_entry_id');
        $this->assertNotNull($journalEntryId);
        $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'POSTED']);

        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $journalEntryId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
    }

    public function test_partial_payment_does_not_complete_a_pending_sale(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);
        $saleId = $created->json('item.id');

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 100, 'payment_method_id' => $cashMethodId]);

        $first->assertOk();
        $first->assertJsonPath('item.status', 'PENDING');
        $first->assertJsonPath('item.paid_amount', 100);
        $first->assertJsonPath('item.balance_due', 187.5);
        $this->assertNull($first->json('item.journal_entry_id'));

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 187.5, 'payment_method_id' => $cashMethodId]);

        $second->assertOk();
        $second->assertJsonPath('item.status', 'COMPLETED');
        $second->assertJsonPath('item.paid_amount', 287.5);
        $second->assertJsonPath('item.balance_due', 0);
        $this->assertNotNull($second->json('item.journal_entry_id'));

        $this->assertDatabaseCount('sale_payments', 2);
    }

    public function test_cash_payment_on_a_pending_sale_creates_a_cash_movement_in_the_open_session(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();
        $sessionId = $this->openCashSession($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 28.75, 'payment_method_id' => $cashMethodId]);

        $payment->assertOk();

        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $sessionId,
            'type' => 'INGRESO',
            'amount' => 28.75,
            'status' => 'ACTIVO',
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $saleId,
            'amount' => 28.75,
            'cash_session_id' => $sessionId,
        ]);
    }

    public function test_non_cash_payment_on_a_pending_sale_does_not_create_a_cash_movement(): void
    {
        $token = $this->seedFixtures();
        $bankMethodId = $this->createPaymentMethod('BANK', 'Transferencia', false);
        $this->openCashSession($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 28.75, 'payment_method_id' => $bankMethodId])
            ->assertOk();

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_registering_payment_rejects_amount_above_balance_due(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 999, 'payment_method_id' => $cashMethodId]);

        $payment->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'PENDING']);
    }

    public function test_registering_payment_rejects_missing_payment_method(): void
    {
        $token = $this->seedFixtures();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 28.75]);

        $payment->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'PENDING']);
    }

    public function test_registering_payment_rejects_payment_method_from_another_company(): void
    {
        $token = $this->seedFixtures();

        $otherCompanyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Otra Empresa', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignMethodId = DB::table('payment_methods')->insertGetId([
            'company_id' => $otherCompanyId, 'code' => 'CASH-OTRA', 'name' => 'Efectivo',
            'type' => 'CASH', 'cash' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 28.75, 'payment_method_id' => $foreignMethodId]);

        $payment->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'PENDING']);
    }

    public function test_registering_payment_rejects_sale_not_in_pending_status(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');
        $created->assertJsonPath('item.status', 'COMPLETED');

        $payment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 28.75, 'payment_method_id' => $cashMethodId]);

        $payment->assertStatus(409);
    }

    public function test_cancelling_a_pending_payment_sale_reverses_inventory_with_no_journal_or_ar_cleanup_needed(): void
    {
        $token = $this->seedFixtures();
        $adminId = $this->createAdminWithPin('1234');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [$this->saleItemPayload(10, 25)],
            ]);
        $saleId = $created->json('item.id');

        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 90,
        ]);

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel", ['authorization_pin' => '1234']);

        $cancel->assertOk();
        $cancel->assertJsonPath('item.status', 'CANCELLED');

        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 100,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->productId, 'movement_type' => 'RETURN_IN', 'quantity' => 10,
        ]);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('accounts_receivable', 0);

        // Auditoria: quien anulo (el usuario logueado) y quien autorizo con
        // su PIN (el administrador) quedan registrados por separado.
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'cancellation_authorized_by' => $adminId,
        ]);
        $sale = DB::table('sales')->where('id', $saleId)->first();
        $this->assertNotNull($sale->cancelled_at);
        $this->assertNotNull($sale->cancelled_by);
    }

    public function test_cancel_requires_an_authorization_pin(): void
    {
        $token = $this->seedFixtures();
        $this->createAdminWithPin('1234');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel");

        $cancel->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'COMPLETED']);
    }

    public function test_cancel_rejects_an_invalid_pin(): void
    {
        $token = $this->seedFixtures();
        $this->createAdminWithPin('1234');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel", ['authorization_pin' => '9999']);

        $cancel->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'COMPLETED']);
    }

    public function test_cancel_rejects_a_pin_that_belongs_to_a_user_without_cancellation_authority(): void
    {
        // El propio usuario logueado (rol "manage" de facturacion, sin
        // eliminar/administrar) se pone un PIN: alcanza para identificarse
        // pero no para autorizar la anulacion de otro.
        $token = $this->seedFixtures();
        $userId = DB::table('users')->where('username', 'tester')->value('id');
        DB::table('users')->where('id', $userId)->update([
            'pin_hash' => Hash::make('5555'), 'pin_generated_at' => now(),
        ]);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel", ['authorization_pin' => '5555']);

        $cancel->assertStatus(403);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'COMPLETED']);
    }

    public function test_cancelling_a_completed_sale_with_a_valid_admin_pin_excludes_it_from_cash_totals(): void
    {
        $token = $this->seedFixtures();
        $this->createAdminWithPin('1234');
        $cashMethodId = $this->createPaymentMethod();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $saleId = $created->json('item.id');
        $created->assertJsonPath('item.status', 'COMPLETED');

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel", ['authorization_pin' => '1234']);
        $cancel->assertOk();

        // Sin ledger de caja que reversar a mano: el resumen de caja solo
        // cuenta ventas COMPLETED, asi que una vez CANCELLED la venta ya no
        // aparece ahi.
        $activeSalesTotal = DB::table('sales')
            ->where('company_id', $this->companyId)
            ->where('status', 'COMPLETED')
            ->count();
        $this->assertSame(0, $activeSalesTotal);
    }

    public function test_index_filters_by_date_range(): void
    {
        $token = $this->seedFixtures();

        $old = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'sale_date' => '2026-01-05',
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $old->assertCreated();

        $recent = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'sale_date' => '2026-06-15',
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $recent->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sales?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($recent->json('item.id')));
        $this->assertFalse($ids->contains($old->json('item.id')));
    }

    public function test_index_filters_by_salesperson_id(): void
    {
        $token = $this->seedFixtures();

        $salespersonRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $salespersonId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $salespersonRoleId, 'uuid' => (string) Str::uuid(),
            'username' => 'vendedor-2', 'password_hash' => bcrypt('password'), 'full_name' => 'Vendedor Dos',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $withSalesperson = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'salesperson_id' => $salespersonId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $withSalesperson->assertCreated();

        $withoutSalesperson = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);
        $withoutSalesperson->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/sales?salesperson_id={$salespersonId}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($withSalesperson->json('item.id')));
        $this->assertFalse($ids->contains($withoutSalesperson->json('item.id')));
    }

    private function createCardPaymentMethod(): int
    {
        return DB::table('payment_methods')->insertGetId([
            'code' => 'CRT', 'name' => 'Tarjeta de Credito', 'type' => 'CARD',
            'card' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_card_sale_without_reference_is_rejected(): void
    {
        $token = $this->seedFixtures();
        $cardMethodId = $this->createCardPaymentMethod();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cardMethodId,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_card_sale_with_reference_persists_it_and_returns_it_in_the_payload(): void
    {
        $token = $this->seedFixtures();
        $cardMethodId = $this->createCardPaymentMethod();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cardMethodId,
                'payment_reference' => 'AUTH-000123',
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.payment_reference', 'AUTH-000123');
        $response->assertJsonPath('item.payment_method_flags.card', true);
        $this->assertDatabaseHas('sales', [
            'id' => $response->json('item.id'),
            'payment_reference' => 'AUTH-000123',
        ]);
    }

    /**
     * Un cliente puede pagar con una mezcla de billetes en las dos monedas
     * a la vez (ej. un billete de $10 y uno de C$500 en la misma venta):
     * ambos montos se guardan por separado (amount_tendered_base y
     * amount_tendered_foreign), no forzados a una sola moneda.
     */
    public function test_cash_sale_with_mixed_currency_payment_persists_both_amounts(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = DB::table('payment_methods')->insertGetId([
            'code' => 'CASH', 'name' => 'Efectivo', 'type' => 'CASH',
            'cash' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $cashMethodId,
                // Total de la venta (con IVA 15%): 1 x 25 = 25 + 3.75 = 28.75.
                // Cliente entrega C$500 + $10 (tasa 36.5): 500 + 365 = 865 recibidos.
                'amount_tendered_base' => 500,
                'amount_tendered_foreign' => 10,
                'change_amount' => 836.25,
                'exchange_rate' => 36.5,
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.amount_tendered_base', 500);
        $response->assertJsonPath('item.amount_tendered_foreign', 10);
        $response->assertJsonPath('item.change_amount', 836.25);
        $this->assertDatabaseHas('sales', [
            'id' => $response->json('item.id'),
            'amount_tendered_base' => 500,
            'amount_tendered_foreign' => 10,
            'change_amount' => 836.25,
        ]);
    }

    public function test_sale_of_a_product_with_warranty_snapshots_it_and_computes_expiration(): void
    {
        $token = $this->seedFixtures();

        DB::table('products')->where('id', $this->productId)->update([
            'has_warranty' => true,
            'warranty_days' => 180,
            'warranty_period_unit' => 'MONTHS',
            'warranty_type' => 'Garantia de fabrica',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'sale_date' => '2026-01-01',
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.items.0.has_warranty', true);
        $response->assertJsonPath('item.items.0.warranty_type', 'Garantia de fabrica');
        $response->assertJsonPath('item.items.0.warranty_expires_at', '2026-06-30');

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $response->json('item.id'),
            'has_warranty' => true,
            'warranty_days' => 180,
        ]);

        $storedExpiration = DB::table('sale_items')->where('sale_id', $response->json('item.id'))->value('warranty_expires_at');
        $this->assertSame('2026-06-30', substr((string) $storedExpiration, 0, 10));
    }

    public function test_credit_sale_payload_includes_due_date_and_late_fee_policy(): void
    {
        $token = $this->seedFixtures();

        DB::table('customers')->where('id', $this->customerId)->update([
            'credit_days' => 30,
            'applies_late_fee' => true,
            'late_fee_percentage' => 2.5,
            'late_fee_period_unit' => 'MONTHS',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'sale_date' => '2026-01-01',
                'items' => [$this->saleItemPayload(1, 25)],
            ]);

        $response->assertCreated();

        $detail = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sales/'.$response->json('item.id'));

        $detail->assertOk();
        $detail->assertJsonPath('item.due_date', '2026-01-31');
        $detail->assertJsonPath('item.customer_applies_late_fee', true);
        $detail->assertJsonPath('item.customer_late_fee_percentage', 2.5);
        $detail->assertJsonPath('item.customer_late_fee_period_unit', 'MONTHS');
    }

    /**
     * Un combo/kit no tiene existencia propia: venderlo debe descontar el
     * inventario de cada componente (product_kit_items), en la cantidad que
     * el kit necesita de cada uno multiplicada por cuantos kits se
     * vendieron — nunca crear un movimiento contra el kit mismo.
     */
    public function test_selling_a_kit_discounts_inventory_from_its_components(): void
    {
        $token = $this->seedFixtures();
        $kitId = $this->seedKitFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [['product_id' => $kitId, 'quantity' => 3, 'unit_price' => 40]],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.status', 'COMPLETED');

        // 3 kits x 2 unidades de A = 6 descontadas de 50; x 1 unidad de B = 3 descontadas de 20.
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentAId, 'warehouse_id' => $this->warehouseId, 'quantity' => 44,
        ]);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentBId, 'warehouse_id' => $this->warehouseId, 'quantity' => 17,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->kitComponentAId, 'movement_type' => 'SALE_EXIT', 'quantity' => 6,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->kitComponentBId, 'movement_type' => 'SALE_EXIT', 'quantity' => 3,
        ]);
        // El kit mismo no es inventariable: ningun movimiento se registra contra el.
        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $kitId]);
    }

    public function test_selling_a_kit_fails_when_a_component_has_insufficient_stock(): void
    {
        $token = $this->seedFixtures();
        $kitId = $this->seedKitFixtures();

        // Componente B solo tiene 20 unidades; 25 kits x 1 c/u = 25 necesarias.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [['product_id' => $kitId, 'quantity' => 25, 'unit_price' => 40]],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentBId, 'warehouse_id' => $this->warehouseId, 'quantity' => 20,
        ]);
    }

    /**
     * Anular una venta con un combo/kit debe devolver el inventario de cada
     * componente exactamente como estaba antes de venderlo (reverso de
     * moveInventoryForKitComponents()), no intentar devolver el kit mismo.
     */
    public function test_cancelling_a_sale_with_a_kit_restores_component_inventory(): void
    {
        $token = $this->seedFixtures();
        $adminId = $this->createAdminWithPin('1234');
        $kitId = $this->seedKitFixtures();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'items' => [['product_id' => $kitId, 'quantity' => 3, 'unit_price' => 40]],
            ]);
        $saleId = $created->json('item.id');

        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentAId, 'warehouse_id' => $this->warehouseId, 'quantity' => 44,
        ]);

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/cancel", ['authorization_pin' => '1234']);

        $cancel->assertOk();
        $cancel->assertJsonPath('item.status', 'CANCELLED');

        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentAId, 'warehouse_id' => $this->warehouseId, 'quantity' => 50,
        ]);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->kitComponentBId, 'warehouse_id' => $this->warehouseId, 'quantity' => 20,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->kitComponentAId, 'movement_type' => 'RETURN_IN', 'quantity' => 6,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->kitComponentBId, 'movement_type' => 'RETURN_IN', 'quantity' => 3,
        ]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'cancellation_authorized_by' => $adminId]);
    }
}
