<?php

namespace Tests\Feature;

use Database\Seeders\AccountingAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre el modulo de Apertura de Caja de punta a punta: abrir/cerrar,
 * movimientos manuales con motivo obligatorio, bloqueo de retiro sin
 * fondos, flujo de autorizacion por monto, candado de terminal, y que una
 * venta en efectivo confirmada durante una apertura se refleje en su
 * resumen financiero (integracion con SalesService).
 */
class CashRegisterModuleTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $branchId;

    private int $warehouseId;

    private int $customerId;

    private int $productId;

    private int $cashRegisterId;

    private int $cashPaymentMethodId;

    /**
     * @return array{0: string, 1: string} [token del cajero, token del supervisor con ver_todas]
     */
    private function seedFixtures(): array
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
            'name' => 'Sucursal Central', 'is_headquarters' => true, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'code' => 'BOD-01',
            'name' => 'Bodega Central', 'type' => 'PRINCIPAL', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente de Prueba',
            'currency_id' => $currencyId, 'credit_limit' => 10000, 'current_balance' => 0, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('taxes')->insert([
            'code' => 'IVA15', 'name' => 'IVA 15%', 'rate' => 15, 'type' => 'VAT',
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PROD-001', 'short_name' => 'Producto de Prueba',
            'type' => 'PRODUCTO', 'status' => 1, 'is_inventory' => true, 'is_service' => false,
            'allow_sale' => true, 'allow_purchase' => true, 'allow_negative_stock' => false,
            'cost' => 10, 'sale_price' => 25, 'tax_type' => 'EXEMPT',
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

        $this->cashPaymentMethodId = DB::table('payment_methods')->insertGetId([
            'code' => 'CASH', 'name' => 'Efectivo', 'type' => 'CASH', 'cash' => true,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AccountingAccountsSeeder())->run();

        $this->cashRegisterId = DB::table('cash_registers')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'code' => 'CAJA-01',
            'name' => 'Caja #1', 'default_currency_id' => $currencyId, 'status' => 'ACTIVA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cajeroRoleId = $this->makeRole('Cajero', ['ver', 'abrir', 'cerrar', 'agregar_efectivo', 'retirar_efectivo', 'realizar_arqueo']);
        $supervisorRoleId = $this->makeRole('Supervisor', ['ver', 'abrir', 'cerrar', 'agregar_efectivo', 'retirar_efectivo', 'ver_todas', 'anular_movimiento', 'autorizar_movimiento', 'realizar_arqueo']);

        $cajeroToken = $this->makeUser('cajero', $cajeroRoleId);
        $supervisorToken = $this->makeUser('supervisor', $supervisorRoleId);

        return [$cajeroToken, $supervisorToken];
    }

    private function makeRole(string $name, array $cajaActions): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($cajaActions as $action) {
            // "permissions" es un catalogo global (module_name+action_name
            // unico), no por empresa: hay que buscarlo antes de insertar
            // para no chocar cuando dos roles de este test comparten una
            // misma accion (ej. "ver").
            $permissionId = DB::table('permissions')
                ->where('module_name', 'cajas')
                ->where('action_name', $action)
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'module_name' => 'cajas', 'action_name' => $action, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $roleId;
    }

    private function grantModulePermission(int $roleId, string $module, string $action = 'manage'): void
    {
        $permissionId = DB::table('permissions')
            ->where('module_name', $module)
            ->where('action_name', $action)
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module, 'action_name' => $action, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $alreadyGranted = DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $alreadyGranted) {
            DB::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function makeUser(string $username, int $roleId): string
    {
        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'role_id' => $roleId,
            'uuid' => (string) Str::uuid(), 'username' => $username, 'password_hash' => bcrypt('password'),
            'full_name' => ucfirst($username), 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = "token-{$username}";

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', $token),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    public function test_open_session_succeeds_and_blocks_double_open(): void
    {
        [$cajero] = $this->seedFixtures();

        $response = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $this->cashRegisterId,
                'opening_amount' => 500,
                'opening_note' => 'Fondo inicial del turno',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.status', 'ABIERTA');
        $response->assertJsonPath('item.opening_amount', 500);
        $sessionId = $response->json('item.id');

        $second = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $this->cashRegisterId,
                'opening_amount' => 100,
            ]);

        $second->assertStatus(409);

        $current = $this->withHeader('Authorization', "Bearer {$cajero}")->getJson('/api/cash-sessions/current');
        $current->assertOk();
        $current->assertJsonPath('item.id', $sessionId);
    }

    public function test_cannot_open_inactive_register(): void
    {
        [$cajero] = $this->seedFixtures();
        DB::table('cash_registers')->where('id', $this->cashRegisterId)->update(['status' => 'INACTIVA']);

        $response = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', ['cash_register_id' => $this->cashRegisterId, 'opening_amount' => 100]);

        $response->assertStatus(422);
    }

    public function test_movement_requires_a_reason(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero);

        $response = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId,
                'type' => 'INGRESO',
                'amount' => 200,
            ]);

        $response->assertStatus(422);
    }

    public function test_withdrawal_cannot_exceed_available_cash(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        $response = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId,
                'type' => 'EGRESO',
                'amount' => 600,
                'reason_text' => 'Retiro de prueba',
            ]);

        $response->assertStatus(422);
    }

    public function test_income_and_withdrawal_update_balance(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'INGRESO', 'amount' => 200, 'reason_text' => 'Fondo adicional',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'EGRESO', 'amount' => 150, 'reason_text' => 'Pago a proveedor',
            ])->assertCreated();

        $summary = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");

        $summary->assertOk();
        $summary->assertJsonPath('item.balance_actual', 550); // 500 + 200 - 150
    }

    public function test_cancelled_movement_no_longer_affects_balance(): void
    {
        [$cajero, $supervisor] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        $movement = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'INGRESO', 'amount' => 200, 'reason_text' => 'Fondo adicional',
            ])->assertCreated();

        $movementId = $movement->json('item.id');

        // Anular es una accion de supervision: el cajero que registro el
        // movimiento no tiene "anular_movimiento" por defecto.
        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/cash-movements/{$movementId}/cancel", ['reason' => 'Registrado por error'])
            ->assertStatus(403);

        $cancel = $this->withHeader('Authorization', "Bearer {$supervisor}")
            ->postJson("/api/cash-movements/{$movementId}/cancel", ['reason' => 'Registrado por error']);
        $cancel->assertOk();
        $cancel->assertJsonPath('item.status', 'ANULADO');

        $summary = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summary->assertJsonPath('item.balance_actual', 500);
    }

    public function test_movement_above_threshold_requires_authorization_before_counting(): void
    {
        [$cajero, $supervisor] = $this->seedFixtures();
        DB::table('cash_registers')->where('id', $this->cashRegisterId)->update(['authorization_threshold' => 300]);
        $sessionId = $this->openSession($cajero, 500);

        $movement = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'INGRESO', 'amount' => 1000, 'reason_text' => 'Fondo grande',
            ]);
        $movement->assertCreated();
        $movement->assertJsonPath('item.status', 'PENDIENTE_AUTORIZACION');
        $movementId = $movement->json('item.id');

        // Todavia no cuenta para el balance mientras esta pendiente.
        $summary = $this->withHeader('Authorization', "Bearer {$cajero}")->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summary->assertJsonPath('item.balance_actual', 500);

        // Un cajero sin "autorizar_movimiento" no puede autorizarlo.
        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/cash-movements/{$movementId}/authorize")
            ->assertStatus(403);

        $authorize = $this->withHeader('Authorization', "Bearer {$supervisor}")
            ->postJson("/api/cash-movements/{$movementId}/authorize");
        $authorize->assertOk();
        $authorize->assertJsonPath('item.status', 'ACTIVO');

        $summaryAfter = $this->withHeader('Authorization', "Bearer {$cajero}")->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summaryAfter->assertJsonPath('item.balance_actual', 1500);
    }

    public function test_cannot_close_with_pending_authorization(): void
    {
        [$cajero] = $this->seedFixtures();
        DB::table('cash_registers')->where('id', $this->cashRegisterId)->update(['authorization_threshold' => 100]);
        $sessionId = $this->openSession($cajero, 500);

        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'INGRESO', 'amount' => 200, 'reason_text' => 'Fondo grande',
            ])->assertCreated();

        $close = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/cash-sessions/{$sessionId}/close", ['counted_cash' => 500]);

        $close->assertStatus(422);
    }

    public function test_close_session_computes_expected_counted_and_difference(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-movements', [
                'cash_session_id' => $sessionId, 'type' => 'INGRESO', 'amount' => 100, 'reason_text' => 'Fondo adicional',
            ])->assertCreated();

        // expected = 500 + 100 = 600; contamos 590 -> faltante de 10.
        $close = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/cash-sessions/{$sessionId}/close", [
                'counted_cash' => 590,
                'closing_note' => 'Faltante detectado en arqueo final',
            ]);

        $close->assertOk();
        $close->assertJsonPath('item.status', 'CERRADA');
        $close->assertJsonPath('item.expected_cash', 600);
        $close->assertJsonPath('item.counted_cash', 590);
        $close->assertJsonPath('item.cash_difference', -10);

        $this->assertDatabaseHas('cash_counts', [
            'cash_session_id' => $sessionId, 'type' => 'CIERRE',
            'expected_amount' => 600, 'counted_amount' => 590, 'difference' => -10,
        ]);

        // La caja vuelve a estar disponible para una nueva apertura.
        $reopen = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', ['cash_register_id' => $this->cashRegisterId, 'opening_amount' => 0]);
        $reopen->assertCreated();
    }

    public function test_completed_cash_sale_is_reflected_in_session_summary(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        // El cajero de este test tambien necesita permiso de facturacion
        // para poder registrar la venta (modulo aparte de "cajas").
        $cajeroRoleId = DB::table('users')->where('company_id', $this->companyId)->where('username', 'cajero')->value('role_id');
        $this->grantModulePermission($cajeroRoleId, 'facturacion');
        $this->grantModulePermission($cajeroRoleId, 'clientes');

        $sale = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'payment_method_id' => $this->cashPaymentMethodId,
                'items' => [['product_id' => $this->productId, 'quantity' => 2, 'unit_price' => 25]],
            ]);

        $sale->assertCreated();
        $sale->assertJsonPath('item.status', 'COMPLETED');

        $this->assertDatabaseHas('sales', [
            'id' => $sale->json('item.id'),
            'cash_session_id' => $sessionId,
        ]);

        $summary = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");

        $summary->assertOk();
        $summary->assertJsonPath('item.cash_sales', 50);
        $summary->assertJsonPath('item.balance_actual', 550); // 500 inicial + 50 de venta en efectivo
    }

    public function test_pending_payment_sale_is_not_reflected_in_session_summary_until_payment_registered(): void
    {
        [$cajero] = $this->seedFixtures();
        $sessionId = $this->openSession($cajero, 500);

        $cajeroRoleId = DB::table('users')->where('company_id', $this->companyId)->where('username', 'cajero')->value('role_id');
        $this->grantModulePermission($cajeroRoleId, 'facturacion');
        $this->grantModulePermission($cajeroRoleId, 'clientes');

        $sale = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/sales', [
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'is_pending_payment' => true,
                'items' => [['product_id' => $this->productId, 'quantity' => 2, 'unit_price' => 25]],
            ]);

        $sale->assertCreated();
        $sale->assertJsonPath('item.status', 'PENDING');
        $saleId = $sale->json('item.id');

        // Mientras no se registre el abono, no debe aparecer en el resumen
        // de la sesion (sigue como si la caja no hubiera recibido nada).
        $summaryBefore = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summaryBefore->assertOk();
        $summaryBefore->assertJsonPath('item.cash_sales', 0);
        $summaryBefore->assertJsonPath('item.balance_actual', 500);

        $payment = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/sales/{$saleId}/payments", ['amount' => 50, 'payment_method_id' => $this->cashPaymentMethodId]);
        $payment->assertOk();
        $payment->assertJsonPath('item.status', 'COMPLETED');

        // El abono en efectivo se registra via un CashMovement (no
        // re-bucketing de la venta en cash_sales, para no duplicar el
        // efectivo contado: ver SalesService::registerPendingPayment()).
        // cash_session_id de la venta queda en null a proposito por la
        // misma razon.
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'cash_session_id' => null,
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $sessionId,
            'type' => 'INGRESO',
            'amount' => 50,
            'status' => 'ACTIVO',
        ]);

        // El efectivo ya cuenta (via credit_collections, no manual_income:
        // el abono de una factura pendiente es un cobro de credito, no un
        // ingreso manual discrecional), pero no se duplica en el bucket
        // "cash_sales" (que solo mira sales.cash_session_id).
        $summaryAfter = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson("/api/cash-sessions/{$sessionId}/summary");
        $summaryAfter->assertOk();
        $summaryAfter->assertJsonPath('item.cash_sales', 0);
        $summaryAfter->assertJsonPath('item.credit_collections', 50);
        $summaryAfter->assertJsonPath('item.manual_income', 0);
        $summaryAfter->assertJsonPath('item.balance_actual', 550);
    }

    public function test_terminal_locked_to_another_register_blocks_opening(): void
    {
        [$cajero] = $this->seedFixtures();

        $otherRegisterId = DB::table('cash_registers')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'code' => 'CAJA-02',
            'name' => 'Caja #2', 'default_currency_id' => 1, 'status' => 'ACTIVA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Se abre CAJA-01 desde una terminal identificada por su codigo.
        $open = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $this->cashRegisterId,
                'opening_amount' => 100,
                'terminal_code' => 'TERM-ABC',
            ]);
        $open->assertCreated();
        $sessionId = $open->json('item.id');

        // Un admin bloquea esa terminal a CAJA-01 (candado de terminal).
        DB::table('terminals')->where('code', 'TERM-ABC')->update(['cash_register_id' => $this->cashRegisterId]);

        // Se cierra la apertura para dejar CAJA-01 disponible de nuevo.
        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson("/api/cash-sessions/{$sessionId}/close", ['counted_cash' => 100])
            ->assertOk();

        // Intentar abrir CAJA-02 desde la misma terminal debe fallar.
        $blocked = $this->withHeader('Authorization', "Bearer {$cajero}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $otherRegisterId,
                'opening_amount' => 100,
                'terminal_code' => 'TERM-ABC',
            ]);

        $blocked->assertStatus(422);
    }

    public function test_monitor_requires_ver_todas_permission(): void
    {
        [$cajero, $supervisor] = $this->seedFixtures();
        $this->openSession($cajero, 500);

        $this->withHeader('Authorization', "Bearer {$cajero}")
            ->getJson('/api/cash-sessions/monitor')
            ->assertStatus(403);

        $monitor = $this->withHeader('Authorization', "Bearer {$supervisor}")->getJson('/api/cash-sessions/monitor');
        $monitor->assertOk();
        $monitor->assertJsonPath('totals.open_count', 1);
    }

    private function openSession(string $token, float $amount = 0, bool $expectCreated = true): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-sessions/open', [
                'cash_register_id' => $this->cashRegisterId,
                'opening_amount' => $amount,
            ]);

        if ($expectCreated) {
            $response->assertCreated();
        }

        return (int) $response->json('item.id');
    }
}
