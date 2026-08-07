<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $supplierId;

    private int $productId;

    /**
     * Crea empresa, moneda, rol con permisos de compras, usuario
     * autenticado, almacen, proveedor, producto con stock inicial (10 uds
     * a costo 10), impuesto IVA 15% por defecto, y el plan de cuentas
     * contable (via el seeder real).
     */
    private function seedFixtures(float $stockQuantity = 10, float $averageCost = 10): string
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

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'compras', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
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

        $this->supplierId = DB::table('suppliers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PRV-000001', 'name' => 'Proveedor de Prueba',
            'currency_id' => $currencyId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15', 'name' => 'IVA 15%', 'rate' => 15, 'type' => 'VAT',
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => $averageCost, 'sale_price' => 25, 'tax_type' => 'TAXABLE', 'tax_percentage' => 15,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->productId,
            'quantity' => $stockQuantity, 'reserved_quantity' => 0, 'average_cost' => $averageCost,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    private function purchaseItemPayload(float $quantity = 5, ?float $unitCost = null): array
    {
        return array_filter([
            'product_id' => $this->productId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ], fn ($value) => $value !== null);
    }

    public function test_confirmed_credit_purchase_increases_stock_and_registers_movement(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->purchaseItemPayload(20, 12)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.status', 'RECEIVED');

        // Costo promedio ponderado: (10*10 + 20*12) / 30 = 340/30 = 11.3333
        $this->assertDatabaseHas('inventory_stock', [
            'company_id' => $this->companyId,
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'quantity' => 30,
        ]);

        $stock = DB::table('inventory_stock')
            ->where('product_id', $this->productId)
            ->first();
        $this->assertEqualsWithDelta(11.3333, (float) $stock->average_cost, 0.001);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $this->companyId,
            'product_id' => $this->productId,
            'movement_type' => 'PURCHASE_ENTRY',
            'quantity' => 20,
            'stock_before' => 10,
            'stock_after' => 30,
        ]);
    }

    public function test_confirmed_purchase_generates_a_balanced_journal_entry(): void
    {
        $token = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->purchaseItemPayload(10, 20)],
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

    public function test_credit_purchase_creates_accounts_payable(): void
    {
        $token = $this->seedFixtures();

        $paymentTermId = DB::table('payment_terms')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CR30', 'name' => 'Credito 30 dias',
            'days' => 30, 'type' => 'CREDIT', 'credit' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'payment_term_id' => $paymentTermId,
                'items' => [$this->purchaseItemPayload(4, 25)],
            ]);

        $response->assertCreated();
        $total = $response->json('item.total');
        $payableId = $response->json('item.accounts_payable_id');

        $this->assertNotNull($payableId);
        $this->assertDatabaseHas('accounts_payable', [
            'id' => $payableId,
            'supplier_id' => $this->supplierId,
            'status' => 'PENDING',
            'balance' => $total,
        ]);
    }

    public function test_cash_purchase_does_not_create_accounts_payable(): void
    {
        $token = $this->seedFixtures();

        $paymentMethodId = DB::table('payment_methods')->insertGetId([
            'code' => 'CASH', 'name' => 'Efectivo', 'type' => 'CASH', 'cash' => true,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $paymentMethodId,
                'items' => [$this->purchaseItemPayload(2, 25)],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.accounts_payable_id', null);
        $this->assertDatabaseCount('accounts_payable', 0);
    }

    public function test_purchase_can_be_cancelled_and_reverses_inventory(): void
    {
        $token = $this->seedFixtures();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'items' => [$this->purchaseItemPayload(5, 10)],
            ]);

        $purchaseId = $create->json('item.id');
        $this->assertDatabaseHas('inventory_stock', ['product_id' => $this->productId, 'quantity' => 15]);

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/purchases/{$purchaseId}/cancel");

        $cancel->assertOk();
        $cancel->assertJsonPath('item.status', 'CANCELLED');
        $this->assertDatabaseHas('inventory_stock', ['product_id' => $this->productId, 'quantity' => 10]);

        $journalEntryId = $create->json('item.journal_entry_id');
        $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'VOID']);
    }
}
