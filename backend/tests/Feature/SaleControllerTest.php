<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

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

        foreach (['facturacion', 'clientes'] as $module) {
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
}
