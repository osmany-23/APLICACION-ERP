<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditDebitNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

    private int $cashRegisterId;

    /**
     * Mismo fixture que SaleControllerTest::seedFixtures(): empresa, rol
     * con permiso de facturacion/clientes/cajas, usuario, almacen,
     * producto con stock (100 uds a costo 10), IVA 15%, caja activa, y el
     * plan de cuentas + tipos de documento (FACT/PROF/NC/ND) via el
     * seeder real.
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
            'company_id' => $this->companyId, 'name' => 'Tester', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['facturacion', 'clientes', 'cajas'] as $module) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module, 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

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

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-01',
            'name' => 'Sucursal Central', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->cashRegisterId = DB::table('cash_registers')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $branchId, 'code' => 'CAJA-01',
            'name' => 'Caja #1', 'default_currency_id' => $currencyId, 'status' => 'ACTIVA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId, 'credit_limit' => 10000, 'current_balance' => 0,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15', 'name' => 'IVA 15%', 'rate' => 15, 'type' => 'VAT',
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 10, 'sale_price' => 25, 'tax_type' => 'TAXABLE', 'tax_percentage' => 15,
            'cost_method' => 'AVERAGE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inventory_stock')->insert([
            'company_id' => $this->companyId, 'warehouse_id' => $this->warehouseId, 'product_id' => $this->productId,
            'quantity' => 100, 'reserved_quantity' => 0, 'average_cost' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('document_types')->insert([
            'code' => 'FACT', 'name' => 'Factura de Venta', 'prefix' => 'FACT', 'next_number' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        return 'test-token';
    }

    private function createPaymentMethod(string $code = 'CASH', string $name = 'Efectivo', bool $cash = true): int
    {
        return DB::table('payment_methods')->insertGetId([
            'company_id' => $this->companyId, 'code' => $code, 'name' => $name, 'type' => $cash ? 'CASH' : 'BANK',
            'cash' => $cash, 'bank' => ! $cash, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
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

    /**
     * Crea y confirma (COMPLETED) una factura de 10 unidades a 25 c/u (15%
     * IVA del fixture => subtotal 250, IVA 37.5, total 287.5), pagada con
     * el metodo indicado (o a credito si se omite). Devuelve
     * [saleId, saleItemId].
     */
    private function createCompletedSale(string $token, ?int $paymentMethodId, float $quantity = 10): array
    {
        $payload = [
            'customer_id' => $this->customerId,
            'warehouse_id' => $this->warehouseId,
            'items' => [['product_id' => $this->productId, 'quantity' => $quantity, 'unit_price' => 25]],
        ];

        if ($paymentMethodId) {
            $payload['payment_method_id'] = $paymentMethodId;
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/sales', $payload);
        $response->assertCreated();
        $response->assertJsonPath('item.status', 'COMPLETED');

        $saleId = (int) $response->json('item.id');
        $saleItemId = (int) $response->json('item.items.0.id');

        return [$saleId, $saleItemId];
    }

    public function test_credit_note_on_a_cash_sale_returns_inventory_and_creates_a_cash_egreso(): void
    {
        $token = $this->seedFixtures();
        $cashMethodId = $this->createPaymentMethod();
        $sessionId = $this->openCashSession($token, 500);

        [$saleId, $saleItemId] = $this->createCompletedSale($token, $cashMethodId, 10);

        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 90,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'Producto danado',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 4]],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.total', 115); // 4 * 25 = 100 + 15% IVA = 115
        $this->assertStringStartsWith('NC-', $response->json('item.number'));

        // Vuelve al inventario.
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 94,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->productId, 'movement_type' => 'RETURN_IN', 'quantity' => 4,
            'reference_table' => 'sales_returns',
        ]);

        // Egreso de caja por el total de la nota.
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $sessionId, 'type' => 'EGRESO', 'amount' => 115, 'status' => 'ACTIVO',
        ]);

        $summary = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summary->assertOk();
        // 500 inicial + 287.5 de la venta de contado (SI se contabiliza via
        // cash_sales, esa sale.cash_session_id correcta apunta a esta
        // sesion) - 115 de la nota de credito.
        $summary->assertJsonPath('item.balance_actual', 672.5);
        // El reintegro de la nota de credito se refleja en "returns", no en
        // "manual_expense" (no es un retiro discrecional).
        $summary->assertJsonPath('item.returns', 115);
        $summary->assertJsonPath('item.manual_expense', 0);

        // Asiento balanceado.
        $returnRow = DB::table('sales_returns')->where('sale_id', $saleId)->first();
        $this->assertNotNull($returnRow->journal_entry_id);
        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $returnRow->journal_entry_id)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
    }

    public function test_credit_note_on_a_credit_sale_reduces_receivable_and_customer_balance_instead_of_cash(): void
    {
        $token = $this->seedFixtures();
        [$saleId, $saleItemId] = $this->createCompletedSale($token, null, 10);

        $sale = DB::table('sales')->where('id', $saleId)->first();
        $this->assertNotNull($sale->accounts_receivable_id);
        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 287.5]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'Descuento acordado',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 4]],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $sale->accounts_receivable_id, 'total_amount' => 172.5, 'balance' => 172.5,
        ]);
        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 172.5]);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_credit_note_rejects_crediting_more_than_was_sold(): void
    {
        $token = $this->seedFixtures();
        [$saleId, $saleItemId] = $this->createCompletedSale($token, $this->createPaymentMethod(), 10);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'Intento invalido',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 11]],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales_returns', 0);
    }

    public function test_credit_note_rejects_crediting_more_than_remaining_across_multiple_notes(): void
    {
        $token = $this->seedFixtures();
        [$saleId, $saleItemId] = $this->createCompletedSale($token, $this->createPaymentMethod(), 10);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'Primera nota',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 7]],
            ])->assertCreated();

        // Solo quedan 3 disponibles; pedir 4 debe rechazarse.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'Segunda nota',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 4]],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales_returns', 1);
    }

    public function test_credit_note_rejects_a_sale_that_is_not_completed(): void
    {
        $token = $this->seedFixtures();

        $draft = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'confirm' => false,
                'items' => [['product_id' => $this->productId, 'quantity' => 1, 'unit_price' => 25]],
            ]);
        $draft->assertCreated();
        $saleId = $draft->json('item.id');
        $saleItemId = $draft->json('item.items.0.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/credit-notes", [
                'reason' => 'No deberia aplicar',
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 1]],
            ]);

        $response->assertStatus(422);
    }

    public function test_debit_note_increases_customer_balance_and_receivable(): void
    {
        $token = $this->seedFixtures();
        [$saleId] = $this->createCompletedSale($token, null, 10);

        $sale = DB::table('sales')->where('id', $saleId)->first();
        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 287.5]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/debit-notes", [
                'reason' => 'Cargo adicional por flete',
                'items' => [['description' => 'Flete adicional', 'quantity' => 1, 'unit_price' => 50]],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.total', 50);
        $this->assertStringStartsWith('ND-', $response->json('item.number'));

        $this->assertDatabaseHas('customers', ['id' => $this->customerId, 'current_balance' => 337.5]);
        $this->assertDatabaseHas('accounts_receivable', [
            'id' => $sale->accounts_receivable_id, 'total_amount' => 337.5, 'balance' => 337.5,
        ]);

        $noteRow = DB::table('sales_debit_notes')->where('sale_id', $saleId)->first();
        $this->assertNotNull($noteRow->journal_entry_id);
        $totals = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $noteRow->journal_entry_id)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        $this->assertEqualsWithDelta((float) $totals->total_debit, (float) $totals->total_credit, 0.01);
    }

    public function test_debit_note_is_blocked_by_credit_limit_unless_overridden(): void
    {
        $token = $this->seedFixtures();
        DB::table('customers')->where('id', $this->customerId)->update(['credit_limit' => 300]);
        [$saleId] = $this->createCompletedSale($token, null, 10); // total 287.5, ya cerca del limite

        $blocked = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/debit-notes", [
                'reason' => 'Cargo grande',
                'items' => [['description' => 'Cargo', 'quantity' => 1, 'unit_price' => 100]],
            ]);
        $blocked->assertStatus(422);

        $overridden = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/debit-notes", [
                'reason' => 'Cargo grande autorizado',
                'override_credit_limit' => true,
                'items' => [['description' => 'Cargo', 'quantity' => 1, 'unit_price' => 100]],
            ]);
        $overridden->assertCreated();
    }

    public function test_debit_note_rejects_a_sale_that_is_not_completed(): void
    {
        $token = $this->seedFixtures();

        $draft = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'confirm' => false,
                'items' => [['product_id' => $this->productId, 'quantity' => 1, 'unit_price' => 25]],
            ]);
        $draft->assertCreated();
        $saleId = $draft->json('item.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/sales/{$saleId}/debit-notes", [
                'reason' => 'No deberia aplicar',
                'items' => [['description' => 'Cargo', 'quantity' => 1, 'unit_price' => 10]],
            ]);

        $response->assertStatus(422);
    }
}
